<?php

declare(strict_types=1);

namespace Tns\Epic\Exceptions;

/**
 * Thrown when an Applied API microservice returns a non-2xx response.
 */
final class ApiException extends AppliedEpicException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly ?string $responseBody = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Build an exception from an HTTP error response, pulling a human-readable
     * detail out of the common error shapes the Applied microservices return.
     */
    public static function fromResponse(int $status, string $body): self
    {
        $message = "Applied API request failed with HTTP {$status}";

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $detail = $decoded['detail']
                ?? $decoded['message']
                ?? $decoded['error_description']
                ?? $decoded['title']
                ?? null;
            if (is_string($detail) && $detail !== '') {
                $message .= ": {$detail}";
            }
        }

        return new self($message, $status, $body);
    }
}
