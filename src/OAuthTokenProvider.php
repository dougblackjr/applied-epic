<?php

declare(strict_types=1);

namespace Tns\Epic;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Tns\Epic\Exceptions\AuthException;

/**
 * Exchanges a Consumer Key + Secret for an OAuth2 bearer token using the
 * client-credentials grant.
 *
 * Tokens are cached on two levels: in memory for the life of the process, and
 * — when a PSR-16 cache is supplied — across processes. An expiry leeway means
 * a token is treated as stale slightly before it actually expires, and a 401
 * from the API forces a fresh exchange via {@see refresh()}.
 */
final class OAuthTokenProvider
{
    private ?Token $token = null;

    /**
     * @param list<string> $scopes OAuth scopes to request, space-joined into the grant.
     */
    public function __construct(
        private readonly HttpClient $http,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $tokenUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        private readonly array $scopes = [],
        private readonly ?CacheInterface $cache = null,
        private readonly string $cacheKey = 'applied-epic.oauth-token',
        private readonly int $expiryLeeway = 60,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Return a valid bearer token, performing a token exchange only when no
     * cached token exists or the cached one is at/near expiry.
     */
    public function getToken(): string
    {
        $token = $this->cachedToken();
        if ($token !== null && !$token->isExpired($this->expiryLeeway)) {
            return $token->accessToken;
        }

        return $this->fetchToken()->accessToken;
    }

    /**
     * Discard every cached token and perform a fresh exchange. Called by the
     * Client after a 401 so a revoked or rotated token cannot wedge requests.
     */
    public function refresh(): string
    {
        $this->token = null;
        $this->cache?->delete($this->cacheKey);

        return $this->fetchToken()->accessToken;
    }

    private function cachedToken(): ?Token
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $stored = $this->cache?->get($this->cacheKey);
        if (is_array($stored)) {
            /** @var array{access_token?: string, expires_at?: int} $stored */
            return $this->token = Token::fromArray($stored);
        }

        return null;
    }

    private function fetchToken(): Token
    {
        $form = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->consumerKey,
            'client_secret' => $this->consumerSecret,
        ];
        if ($this->scopes !== []) {
            $form['scope'] = implode(' ', $this->scopes);
        }

        $request = $this->requestFactory->createRequest('POST', $this->tokenUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(http_build_query($form)));

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new AuthException('Token request transport error: ' . $e->getMessage(), 0, $e);
        }

        $body = (string) $response->getBody();
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new AuthException("Token request failed with HTTP {$status}: {$body}");
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['access_token']) || !is_string($data['access_token'])) {
            throw new AuthException('Token response did not contain an access_token.');
        }

        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;
        $token = new Token($data['access_token'], time() + $expiresIn);

        $this->token = $token;
        if ($this->cache !== null) {
            // Expire the cache entry a little before the token itself so a
            // shared cache never hands back a token that is already stale.
            $ttl = max(1, $expiresIn - $this->expiryLeeway);
            $this->cache->set($this->cacheKey, $token->toArray(), $ttl);
        }

        $this->logger?->debug('Acquired Applied API bearer token', ['expires_in' => $expiresIn]);

        return $token;
    }
}
