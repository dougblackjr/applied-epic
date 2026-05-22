<?php

declare(strict_types=1);

namespace Tns\Epic;

/**
 * An OAuth2 bearer token paired with the absolute time at which it expires.
 */
final class Token
{
    public function __construct(
        public readonly string $accessToken,
        public readonly int $expiresAt,
    ) {
    }

    /**
     * True when the token has expired, or will within $leeway seconds. The
     * leeway guards against a token that is valid when checked but expires
     * in flight before the request reaches Applied.
     */
    public function isExpired(int $leeway = 0, ?int $now = null): bool
    {
        $now ??= time();

        return ($now + $leeway) >= $this->expiresAt;
    }

    /**
     * @return array{access_token: string, expires_at: int}
     */
    public function toArray(): array
    {
        return ['access_token' => $this->accessToken, 'expires_at' => $this->expiresAt];
    }

    /**
     * @param array{access_token?: string, expires_at?: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self((string) ($data['access_token'] ?? ''), (int) ($data['expires_at'] ?? 0));
    }
}
