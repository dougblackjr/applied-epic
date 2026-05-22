<?php

declare(strict_types=1);

namespace Tns\Epic\Tests;

use Tns\Epic\Environment;
use Tns\Epic\Exceptions\AppliedEpicException;

final class EnvironmentTest extends TestCase
{
    public function test_production_targets_the_live_hosts(): void
    {
        $env = Environment::resolve('production');

        self::assertSame('https://api.myappliedproducts.com', $env->apiBaseUri);
        self::assertSame('https://api.appliedsystems.com/v1/auth/connect/token', $env->tokenUrl);
    }

    public function test_mock_targets_the_mock_hosts(): void
    {
        $env = Environment::resolve('mock');

        self::assertSame('https://api.mock.myappliedproducts.com', $env->apiBaseUri);
        self::assertSame('https://api.mock.myappliedproducts.com/v1/auth/connect/token', $env->tokenUrl);
    }

    public function test_a_custom_environment_passes_through_unchanged(): void
    {
        $custom = new Environment('https://epic.internal.example', 'https://auth.internal.example/token');

        self::assertSame($custom, Environment::resolve($custom));
    }

    public function test_an_unknown_environment_name_is_rejected(): void
    {
        $this->expectException(AppliedEpicException::class);

        Environment::resolve('staging-3');
    }
}
