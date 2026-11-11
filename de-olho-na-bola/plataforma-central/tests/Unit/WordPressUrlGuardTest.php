<?php

namespace Tests\Unit;

use App\Exceptions\WordPressConnectorException;
use App\Services\WordPressUrlGuard;
use Tests\TestCase;

class WordPressUrlGuardTest extends TestCase
{
    public function test_local_wordpress_url_is_normalized_and_its_ip_is_pinned(): void
    {
        $guard = new WordPressUrlGuard;
        $url = $guard->normalize('http://127.0.0.1:8080/wordpress/');

        $this->assertSame('http://127.0.0.1:8080/wordpress', $url);
        $this->assertSame(
            ['curl' => [CURLOPT_RESOLVE => ['127.0.0.1:8080:127.0.0.1']]],
            $guard->curlOptions($url)
        );
    }

    public function test_url_with_embedded_credentials_or_query_is_rejected(): void
    {
        $guard = new WordPressUrlGuard;

        foreach ([
            'https://user:password@example.com',
            'https://example.com/?target=internal',
            'https://example.com/#fragment',
            'ftp://example.com',
        ] as $url) {
            try {
                $guard->normalize($url);
                $this->fail('Invalid connection URL was accepted: '.$url);
            } catch (WordPressConnectorException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_ipv6_urls_keep_the_required_brackets(): void
    {
        $guard = new WordPressUrlGuard;

        $this->assertSame('http://[::1]:8080/wordpress', $guard->normalize('http://[::1]:8080/wordpress/'));
    }

    public function test_production_connection_rejects_private_ip_addresses(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->expectException(WordPressConnectorException::class);
        (new WordPressUrlGuard)->curlOptions('https://127.0.0.1');
    }
}
