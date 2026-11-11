<?php

namespace App\Services;

use App\Exceptions\WordPressConnectorException;

class WordPressUrlGuard
{
    public function normalize(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new WordPressConnectorException('Informe uma URL válida para o site WordPress.');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new WordPressConnectorException('A URL deve usar HTTP ou HTTPS.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new WordPressConnectorException('A URL não pode conter credenciais, parâmetros ou fragmentos.');
        }

        if ($scheme !== 'https' && ! app()->environment('local', 'testing')) {
            throw new WordPressConnectorException('Sites fora do ambiente local precisam usar HTTPS.');
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $formattedHost = str_contains($host, ':') ? '['.$host.']' : $host;
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? '/'.trim($parts['path'], '/') : '';

        return $scheme.'://'.$formattedHost.$port.$path;
    }

    /**
     * Resolves and pins all addresses so the HTTP client cannot follow a later DNS change.
     *
     * @return array{curl: array<int, list<string>>}
     */
    public function curlOptions(string $baseUrl): array
    {
        $parts = parse_url($baseUrl);
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        $scheme = (string) ($parts['scheme'] ?? '');
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $addresses = $this->resolveAddresses($host);

        if ($addresses === []) {
            throw new WordPressConnectorException('O domínio do site não pôde ser resolvido.');
        }

        if (! app()->environment('local', 'testing')) {
            foreach ($addresses as $address) {
                if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new WordPressConnectorException('A URL do site aponta para um endereço de rede não permitido.');
                }
            }
        }

        $resolve = array_map(
            static fn (string $address): string => $host.':'.$port.(str_contains($address, ':') ? ':['.$address.']' : ':'.$address),
            $addresses
        );

        return ['curl' => [CURLOPT_RESOLVE => $resolve]];
    }

    /**
     * @return list<string>
     */
    private function resolveAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        if ($host === 'localhost') {
            return ['127.0.0.1', '::1'];
        }

        $addresses = gethostbynamel($host) ?: [];
        $records = dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique(array_filter(
            $addresses,
            static fn (string $address): bool => (bool) filter_var($address, FILTER_VALIDATE_IP)
        )));
    }
}
