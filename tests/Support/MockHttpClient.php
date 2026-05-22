<?php

declare(strict_types=1);

namespace Tns\Epic\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client for tests. Every request is delegated to a handler callable
 * and recorded, so tests can assert on what the client sent — how many token
 * exchanges happened, which pages were fetched, and in what order.
 */
final class MockHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var callable(RequestInterface, int): ResponseInterface */
    private $handler;

    /**
     * @param callable(RequestInterface, int): ResponseInterface $handler
     *        Receives the request and its zero-based call index.
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $index = count($this->requests);
        $this->requests[] = $request;

        return ($this->handler)($request, $index);
    }

    /** Number of recorded requests whose URI contains $needle. */
    public function countMatching(string $needle): int
    {
        return count(array_filter(
            $this->requests,
            static fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), $needle),
        ));
    }
}
