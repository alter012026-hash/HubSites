<?php

namespace App\Jobs;

use App\Exceptions\WordPressConnectorException;
use App\Models\AuditEvent;
use App\Models\Site;
use App\Services\WordPressConnector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class TestWordPressSite implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $uniqueFor = 90;

    public function __construct(public readonly int $siteId, public readonly ?int $actorId = null) {}

    public function uniqueId(): string
    {
        return 'site-connection-'.$this->siteId;
    }

    public function handle(WordPressConnector $connector): void
    {
        $site = Site::query()->findOrFail($this->siteId);
        if (in_array($site->connection_status, ['paused', 'disconnected'], true)) {
            return;
        }

        try {
            $result = $connector->testConnection($site);
            $site->refresh();
            if (in_array($site->connection_status, ['paused', 'disconnected'], true)) {
                return;
            }

            $site->update([
                'connection_status' => 'connected',
                'connection_message' => 'Conexão e permissões verificadas.',
                'wp_version' => $result['wp_version'],
                'capabilities' => $result['capabilities'],
                'last_checked_at' => now(),
            ]);

            AuditEvent::query()->create([
                'organization_id' => $site->organization_id,
                'actor_id' => $this->actorId,
                'action' => 'site.connection_tested',
                'subject_type' => Site::class,
                'subject_id' => $site->id,
                'result' => 'success',
                'message' => 'Conexão e permissões verificadas.',
                'context' => ['wp_version' => $result['wp_version']],
            ]);
        } catch (Throwable $exception) {
            $message = $exception instanceof WordPressConnectorException
                ? $exception->getMessage()
                : 'Falha inesperada ao verificar a conexão WordPress.';

            $site->refresh();
            if (in_array($site->connection_status, ['paused', 'disconnected'], true)) {
                return;
            }

            $site->update([
                'connection_status' => 'error',
                'connection_message' => $message,
                'last_checked_at' => now(),
            ]);
            AuditEvent::query()->create([
                'organization_id' => $site->organization_id,
                'actor_id' => $this->actorId,
                'action' => 'site.connection_tested',
                'subject_type' => Site::class,
                'subject_id' => $site->id,
                'result' => 'error',
                'message' => $message,
            ]);

            if ($exception instanceof WordPressConnectorException) {
                throw $exception;
            }

            throw new WordPressConnectorException($message);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $site = Site::query()->find($this->siteId);
        if (! $site || in_array($site->connection_status, ['paused', 'disconnected'], true)) {
            return;
        }

        $site->update([
            'connection_status' => 'error',
            'connection_message' => $exception instanceof WordPressConnectorException
                ? $exception->getMessage()
                : 'Falha inesperada ao verificar a conexão WordPress.',
            'last_checked_at' => now(),
        ]);
    }
}
