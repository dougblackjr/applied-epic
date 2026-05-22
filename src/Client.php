<?php

declare(strict_types=1);

namespace Tns\Epic;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Tns\Epic\Exceptions\ApiException;

/**
 * Transport over a PSR-18 HTTP client for the modern Applied API.
 *
 * Responsibilities: prefix relative paths with the configured API host, attach
 * the OAuth bearer token, speak HAL+JSON, retry once on a 401 with a refreshed
 * token, and walk limit/offset pagination yielding embedded records.
 */
final class Client
{
    private const DEFAULT_PAGE_SIZE = 100;

    public function __construct(
        private readonly HttpClient $http,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly OAuthTokenProvider $tokenProvider,
        private readonly string $apiBaseUri,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * GET a HAL+JSON resource and return it decoded as an associative array.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $response = $this->send('GET', $path, $query);

        $body = (string) $response->getBody();
        if (trim($body) === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new ApiException("Expected a JSON object from {$path}, received: {$body}");
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Walk limit/offset pagination, yielding every record found under
     * `_embedded.<embeddedKey>` across all pages. The `limit` in $query sets
     * the page size; pagination itself is transparent to the caller.
     *
     * @param array<string, mixed> $query
     * @return \Generator<int, array<string, mixed>>
     */
    public function paginate(string $path, string $embeddedKey, array $query = []): \Generator
    {
        $limit = (int) ($query['limit'] ?? self::DEFAULT_PAGE_SIZE);
        if ($limit < 1) {
            $limit = self::DEFAULT_PAGE_SIZE;
        }
        $offset = max(0, (int) ($query['offset'] ?? 0));
        $query['limit'] = $limit;

        do {
            $query['offset'] = $offset;
            $page = $this->get($path, $query);

            $records = $page['_embedded'][$embeddedKey] ?? [];
            if (!is_array($records)) {
                $records = [];
            }
            foreach ($records as $record) {
                yield $record;
            }

            $count = count($records);
            $offset += $count;

            $total = isset($page['total']) ? (int) $page['total'] : null;
            $hasLinks = isset($page['_links']) && is_array($page['_links']);
            $hasNext = $hasLinks && isset($page['_links']['next']);

            // A full page hints there is more; a known total or an explicit
            // absence of a `next` link lets us stop without a wasted request.
            $more = $count > 0 && $count === $limit;
            if ($more && $total !== null) {
                $more = $offset < $total;
            }
            if ($more && $hasLinks && !$hasNext) {
                $more = false;
            }
        } while ($more);
    }

    /**
     * GET a resource and return its raw response body as a stream — used for
     * binary downloads such as attachment files.
     *
     * @param array<string, mixed> $query
     */
    public function getStream(string $path, array $query = []): StreamInterface
    {
        return $this->send('GET', $path, $query)->getBody();
    }

    /** The configured API host, e.g. https://api.mock.myappliedproducts.com */
    public function apiBaseUri(): string
    {
        return $this->apiBaseUri;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function send(string $method, string $path, array $query): ResponseInterface
    {
        $uri = $this->buildUri($path, $query);

        $response = $this->dispatch($method, $uri, $this->tokenProvider->getToken());

        if ($response->getStatusCode() === 401) {
            $this->logger?->info('Applied API returned 401; refreshing token and retrying', ['uri' => $uri]);
            $response = $this->dispatch($method, $uri, $this->tokenProvider->refresh());
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw ApiException::fromResponse($status, (string) $response->getBody());
        }

        return $response;
    }

    private function dispatch(string $method, string $uri, string $token): ResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, $uri)
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Accept', 'application/hal+json');

        try {
            return $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new ApiException("HTTP transport error for {$uri}: " . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildUri(string $path, array $query): string
    {
        $uri = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : rtrim($this->apiBaseUri, '/') . '/' . ltrim($path, '/');

        if ($query !== []) {
            $separator = str_contains($uri, '?') ? '&' : '?';
            $uri .= $separator . http_build_query($this->normalizeQuery($query));
        }

        return $uri;
    }

    /**
     * The Applied microservices expect comma-separated multi-values (OpenAPI
     * `style: form, explode: false`) and literal `true`/`false` for booleans,
     * so flatten arrays and booleans before query-string encoding.
     *
     * @param array<string, mixed> $query
     * @return array<string, string|int|float>
     */
    private function normalizeQuery(array $query): array
    {
        $normalized = [];
        foreach ($query as $key => $value) {
            if (is_bool($value)) {
                $normalized[$key] = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $normalized[$key] = implode(',', array_map(
                    static fn ($item): string => is_bool($item)
                        ? ($item ? 'true' : 'false')
                        : (string) $item,
                    $value,
                ));
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
