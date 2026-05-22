<?php

declare(strict_types=1);

namespace Tns\Epic;

use Tns\Epic\Exceptions\AppliedEpicException;

/**
 * A connection target for the modern Applied API.
 *
 * The OAuth2 token endpoint lives on a different host than the API microservices,
 * so both are captured here. Use the named constructors for the standard
 * environments, or construct directly for a custom deployment.
 */
final class Environment
{
    public function __construct(
        public readonly string $apiBaseUri,
        public readonly string $tokenUrl,
    ) {
    }

    public static function production(): self
    {
        return new self(
            apiBaseUri: 'https://api.myappliedproducts.com',
            tokenUrl: 'https://api.appliedsystems.com/v1/auth/connect/token',
        );
    }

    public static function mock(): self
    {
        return new self(
            apiBaseUri: 'https://api.mock.myappliedproducts.com',
            tokenUrl: 'https://api.mock.myappliedproducts.com/v1/auth/connect/token',
        );
    }

    /**
     * Resolve a 'production'/'mock' name into an Environment. An Environment
     * instance is passed through unchanged so callers can supply a custom host.
     */
    public static function resolve(string|self $environment): self
    {
        if ($environment instanceof self) {
            return $environment;
        }

        return match (strtolower($environment)) {
            'production', 'prod', 'live' => self::production(),
            'mock', 'sandbox', 'test' => self::mock(),
            default => throw new AppliedEpicException(
                "Unknown environment '{$environment}'. Use 'production' or 'mock', "
                . 'or pass an Environment instance.'
            ),
        };
    }
}
