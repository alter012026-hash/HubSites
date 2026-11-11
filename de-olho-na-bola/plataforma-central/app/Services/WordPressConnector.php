<?php

namespace App\Services;

use App\Exceptions\WordPressConnectorException;
use App\Models\Article;
use App\Models\Site;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WordPressConnector
{
    public function __construct(private readonly WordPressUrlGuard $urlGuard) {}

    /**
     * @return array{wp_version: string|null, capabilities: array<string, bool>}
     */
    public function testConnection(Site $site): array
    {
        $identity = $this->send($site, 'GET', '/wp-json/wp/v2/users/me?context=view');
        $this->ensureSuccess($identity, 'autenticar');

        $response = $this->send($site, 'GET', '/wp-json/bec/v1/connection');
        $this->ensureSuccess($response, 'consultar permissões');
        $data = $response->json();
        $capabilities = is_array($data) && isset($data['capabilities']) && is_array($data['capabilities'])
            ? $data['capabilities']
            : [];

        foreach (['create_posts', 'upload_files', 'assign_categories'] as $required) {
            if (! array_key_exists($required, $capabilities) || ! is_bool($capabilities[$required])) {
                throw new WordPressConnectorException('O plugin conector não retornou o mapa de permissões esperado.');
            }
        }

        if (! $capabilities['create_posts']) {
            throw new WordPressConnectorException('A conexão autenticou, mas o usuário não pode criar rascunhos. Conceda a permissão de editor ou superior.');
        }

        return [
            'wp_version' => isset($data['wp_version']) ? (string) $data['wp_version'] : null,
            'capabilities' => [
                'create_posts' => $capabilities['create_posts'],
                'upload_files' => $capabilities['upload_files'],
                'assign_categories' => $capabilities['assign_categories'],
            ],
        ];
    }

    /**
     * @return array{id: int, link: string, status: string}
     */
    public function createDraft(Site $site, Article $article): array
    {
        $response = $this->send(
            $site,
            'POST',
            '/wp-json/bec/v1/central-drafts',
            [
                'idempotency_key' => $article->uuid,
                'title' => $article->title,
                'content' => $article->content,
            ]
        );
        $this->ensureSuccess($response, 'enviar o rascunho');

        $data = $response->json();
        if (! is_array($data) || ! isset($data['id'], $data['link']) || ! is_numeric($data['id']) || ! is_string($data['link'])) {
            throw new WordPressConnectorException('O WordPress respondeu sem confirmar o rascunho. É seguro tentar novamente.');
        }

        $remoteUrl = parse_url($data['link']);
        $siteUrl = parse_url($site->base_url);
        if (
            ! is_array($remoteUrl)
            || ! isset($remoteUrl['scheme'], $remoteUrl['host'])
            || ! in_array(strtolower($remoteUrl['scheme']), ['http', 'https'], true)
            || strtolower($remoteUrl['host']) !== strtolower((string) ($siteUrl['host'] ?? ''))
        ) {
            throw new WordPressConnectorException('O WordPress retornou um endereço de rascunho inválido.');
        }

        return [
            'id' => (int) $data['id'],
            'link' => $data['link'],
            'status' => isset($data['status']) && is_string($data['status']) ? $data['status'] : 'draft',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function send(Site $site, string $method, string $path, ?array $payload = null): Response
    {
        $baseUrl = app(WordPressUrlGuard::class)->normalize($site->base_url);
        $options = $this->urlGuard->curlOptions($baseUrl);
        $request = Http::acceptJson()
            ->withBasicAuth($site->wp_username, str_replace(' ', '', (string) $site->application_password))
            ->connectTimeout(5)
            ->timeout(15)
            ->withOptions($options + ['allow_redirects' => false]);

        try {
            return $payload === null
                ? $request->get($baseUrl.$path)
                : $request->send($method, $baseUrl.$path, ['json' => $payload]);
        } catch (ConnectionException) {
            throw new WordPressConnectorException('Não foi possível conectar ao WordPress. Verifique a URL, o HTTPS e a disponibilidade do site.');
        }
    }

    private function ensureSuccess(Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        $message = match ($response->status()) {
            401 => 'Autenticação recusada. Confira o usuário e a senha de aplicação do WordPress.',
            403 => 'O usuário autenticou, mas não tem permissão para '.$operation.'.',
            404 => 'O plugin De Olho na Bola Core não foi encontrado ou está desativado no site.',
            429 => 'O WordPress limitou as requisições. Aguarde antes de tentar novamente.',
            default => $response->serverError()
                ? 'O WordPress apresentou uma falha temporária ao '.$operation.'.'
                : 'Não foi possível '.$operation.' no WordPress (HTTP '.$response->status().').',
        };

        throw new WordPressConnectorException($message);
    }
}
