<?php

declare(strict_types=1);

namespace Tns\Epic\Tests;

use Tns\Epic\OAuthTokenProvider;
use Tns\Epic\Tests\Support\ArrayCache;
use Tns\Epic\Tests\Support\MockHttpClient;

final class OAuthTokenProviderTest extends TestCase
{
    private const TOKEN_URL = 'https://api.mock.myappliedproducts.com/v1/auth/connect/token';

    public function test_token_is_fetched_once_and_reused_in_memory(): void
    {
        $http = new MockHttpClient(fn () => $this->jsonResponse(200, $this->fixture('token.json')));
        $provider = $this->provider($http);

        $first = $provider->getToken();
        $second = $provider->getToken();

        self::assertSame('mock-bearer-token-001', $first);
        self::assertSame($first, $second);
        self::assertSame(1, $http->countMatching('/auth/connect/token'));
    }

    public function test_psr16_cache_is_shared_across_provider_instances(): void
    {
        $http = new MockHttpClient(fn () => $this->jsonResponse(200, $this->fixture('token.json')));
        $cache = new ArrayCache();

        // A fresh provider has no in-memory token, so without the shared cache
        // it would exchange again. With it, the second instance reuses the token.
        $this->provider($http, $cache)->getToken();
        $this->provider($http, $cache)->getToken();

        self::assertSame(1, $http->countMatching('/auth/connect/token'));
    }

    public function test_expired_token_triggers_a_new_exchange(): void
    {
        // expires_in of 1s against a 60s leeway means the token is always stale.
        $body = (string) json_encode(['access_token' => 'short-lived', 'expires_in' => 1]);
        $http = new MockHttpClient(fn () => $this->jsonResponse(200, $body));

        $provider = $this->provider($http, leeway: 60);
        $provider->getToken();
        $provider->getToken();

        self::assertSame(2, $http->countMatching('/auth/connect/token'));
    }

    public function test_refresh_discards_the_cached_token_and_re_exchanges(): void
    {
        $http = new MockHttpClient(fn () => $this->jsonResponse(200, $this->fixture('token.json')));
        $cache = new ArrayCache();
        $provider = $this->provider($http, $cache);

        $provider->getToken();
        $provider->refresh();
        $provider->getToken();

        // Initial exchange + the forced refresh; the post-refresh getToken is cached.
        self::assertSame(2, $http->countMatching('/auth/connect/token'));
    }

    public function test_token_request_is_a_form_encoded_client_credentials_grant(): void
    {
        $http = new MockHttpClient(fn () => $this->jsonResponse(200, $this->fixture('token.json')));

        $this->provider($http)->getToken();

        $request = $http->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame((string) $request->getUri(), self::TOKEN_URL);
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));

        parse_str((string) $request->getBody(), $form);
        self::assertSame('client_credentials', $form['grant_type']);
        self::assertSame('consumer-key', $form['client_id']);
        self::assertSame('consumer-secret', $form['client_secret']);
        self::assertSame('epic/policies:read', $form['scope']);
    }

    public function test_a_failed_exchange_throws_an_auth_exception(): void
    {
        $http = new MockHttpClient(fn () => $this->jsonResponse(401, '{"error":"invalid_client"}'));

        $this->expectException(\Tns\Epic\Exceptions\AuthException::class);

        $this->provider($http)->getToken();
    }

    private function provider(MockHttpClient $http, ?ArrayCache $cache = null, int $leeway = 60): OAuthTokenProvider
    {
        return new OAuthTokenProvider(
            http: $http,
            requestFactory: $this->psr17,
            streamFactory: $this->psr17,
            tokenUrl: self::TOKEN_URL,
            consumerKey: 'consumer-key',
            consumerSecret: 'consumer-secret',
            scopes: ['epic/policies:read'],
            cache: $cache,
            expiryLeeway: $leeway,
        );
    }
}
