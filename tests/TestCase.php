<?php

declare(strict_types=1);

namespace Tns\Epic\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Shared base for the package tests: a PSR-17 factory and helpers for loading
 * HAL fixtures and building canned PSR-7 responses.
 */
abstract class TestCase extends BaseTestCase
{
    protected Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    /** Load a fixture file from tests/fixtures verbatim. */
    protected function fixture(string $name): string
    {
        $path = __DIR__ . '/fixtures/' . $name;
        if (!is_file($path)) {
            $this->fail("Missing fixture: {$name}");
        }

        return (string) file_get_contents($path);
    }

    protected function response(int $status, string $body, string $contentType = 'application/hal+json'): Response
    {
        return new Response($status, ['Content-Type' => $contentType], $body);
    }

    protected function jsonResponse(int $status, string $body): Response
    {
        return $this->response($status, $body, 'application/json');
    }
}
