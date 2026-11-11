<?php

namespace App\Jobs;

use App\Exceptions\WordPressConnectorException;
use App\Models\Article;
use App\Models\AuditEvent;
use App\Models\Site;
use App\Services\WordPressConnector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendArticleToWordPress implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $articleId, public readonly ?int $actorId = null) {}

    public function uniqueId(): string
    {
        return 'article-publication-'.$this->articleId;
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(WordPressConnector $connector): void
    {
        $article = DB::transaction(function (): ?Article {
            $article = Article::query()->lockForUpdate()->findOrFail($this->articleId);
            if ($article->remote_post_id !== null) {
                return null;
            }
            if (! in_array($article->status, ['queued', 'send_failed'], true)) {
                return null;
            }

            $site = Site::query()
                ->whereKey($article->site_id)
                ->where('organization_id', $article->organization_id)
                ->firstOrFail();
            if ($site->connection_status !== 'connected' || ! $site->application_password) {
                throw new WordPressConnectorException('Teste e reative a conexão do site antes de enviar o rascunho.');
            }

            $article->update(['status' => 'sending', 'last_error' => null]);

            return $article->fresh();
        });

        if (! $article) {
            return;
        }

        try {
            $site = Site::query()->findOrFail($article->site_id);
            $remote = $connector->createDraft($site, $article);

            DB::transaction(function () use ($article, $remote): void {
                $locked = Article::query()->lockForUpdate()->findOrFail($article->id);
                $locked->update([
                    'status' => 'sent',
                    'remote_post_id' => $remote['id'],
                    'remote_status' => $remote['status'],
                    'remote_url' => $remote['link'],
                    'last_error' => null,
                ]);
                AuditEvent::query()->create([
                    'organization_id' => $locked->organization_id,
                    'actor_id' => $this->actorId,
                    'action' => 'article.sent_as_draft',
                    'subject_type' => Article::class,
                    'subject_id' => $locked->id,
                    'result' => 'success',
                    'message' => 'Rascunho enviado ao WordPress; nenhuma publicação pública foi feita.',
                    'context' => ['remote_post_id' => $remote['id'], 'remote_status' => $remote['status']],
                ]);
            });
        } catch (Throwable $exception) {
            $message = $exception instanceof WordPressConnectorException
                ? $exception->getMessage()
                : 'Falha inesperada ao enviar o rascunho ao WordPress.';

            DB::transaction(function () use ($article, $message): void {
                $locked = Article::query()->lockForUpdate()->find($article->id);
                if (! $locked || $locked->remote_post_id !== null) {
                    return;
                }

                $locked->update(['status' => 'send_failed', 'last_error' => $message]);
                AuditEvent::query()->create([
                    'organization_id' => $locked->organization_id,
                    'actor_id' => $this->actorId,
                    'action' => 'article.send_failed',
                    'subject_type' => Article::class,
                    'subject_id' => $locked->id,
                    'result' => 'error',
                    'message' => $message,
                ]);
            });

            if ($exception instanceof WordPressConnectorException) {
                throw $exception;
            }

            throw new WordPressConnectorException($message);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $article = Article::query()->find($this->articleId);
        if (! $article || $article->remote_post_id !== null) {
            return;
        }

        $message = $exception instanceof WordPressConnectorException
            ? $exception->getMessage()
            : 'Falha inesperada ao enviar o rascunho ao WordPress.';
        $article->update(['status' => 'send_failed', 'last_error' => $message]);
    }
}
