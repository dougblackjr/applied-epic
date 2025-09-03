<?php
namespace Tns\Epic\Http;

use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

final class BaseClient
{
    private string $baseUri;
    private string $authKey;
    private string $database;
    private ?string $accept;
    private HttpClient $http;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private ?LoggerInterface $logger;
    private int $maxRetries;
    private int $initialDelayMs;

    public function __construct(
        HttpClient $http,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $baseUri,
        string $authKey,
        string $database,
        ?string $accept = 'application/json',
        ?LoggerInterface $logger = null,
        int $maxRetries = 3,
        int $initialDelayMs = 200
    ) {
        $this->http = $http;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUri = rtrim($baseUri, '/');
        $this->authKey = $authKey;
        $this->database = $database;
        $this->accept = $accept;
        $this->logger = $logger;
        $this->maxRetries = max(0, $maxRetries);
        $this->initialDelayMs = max(0, $initialDelayMs);
    }

    /**
     * Send a request. If $json is not null, it's encoded and sent as the body.
     * Returns [statusCode, headersArray, bodyString].
     */
    public function request(string $method, string $path, array $query = [], ?array $json = null): array
    {
        $uri = $this->baseUri . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $uri .= '?' . http_build_query($query);
        }

        $req = $this->requestFactory->createRequest($method, $uri)
            ->withHeader('AuthenticationKey', $this->authKey)
            ->withHeader('DatabaseName', $this->database)
            ->withHeader('Content-Type', 'application/json');

        if ($this->accept) {
            $req = $req->withHeader('Accept', $this->accept);
        }

        if ($json !== null) {
            $body = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                throw new \InvalidArgumentException('Failed to JSON-encode request body.');
            }
            $req = $req->withBody($this->streamFactory->createStream($body));
        }

        $attempt = 0;
        $delayMs = $this->initialDelayMs;

        do {
            $attempt++;
            try {
                $res = $this->http->sendRequest($req);
            } catch (\Throwable $e) {
                if ($attempt <= $this->maxRetries) {
                    $this->sleepMs($delayMs);
                    $delayMs *= 2;
                    $this->log('warning', 'HTTP transport exception, retrying', ['attempt' => $attempt, 'error' => $e->getMessage()]);
                    continue;
                }
                throw $e;
            }

            $status = $res->getStatusCode();
            $headers = [];
            foreach ($res->getHeaders() as $k => $vals) {
                $headers[$k] = $vals;
            }
            $body = (string) $res->getBody();

            if ($status >= 200 && $status < 300) {
                return [$status, $headers, $body];
            }

            // Retry on 409 (lock/conflict) and transient 5xx
            if (($status === 409 || ($status >= 500 && $status <= 599)) && $attempt <= $this->maxRetries) {
                $this->log('notice', 'Retrying after non-2xx status', ['status' => $status, 'attempt' => $attempt]);
                $this->sleepMs($delayMs);
                $delayMs *= 2;
                continue;
            }

            throw \Tns\Epic\Exceptions\EpicApiException::fromHttp($status, $body);

        } while ($attempt <= $this->maxRetries);

        // Unreachable
        throw new \RuntimeException('Request failed unexpectedly.');
    }

    private function sleepMs(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    private function log(string $level, string $msg, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log($level, $msg, $context);
        }
    }
}
