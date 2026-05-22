<?php

declare(strict_types=1);

namespace Tns\Epic\Tests\Support;

use Psr\SimpleCache\CacheInterface;

/**
 * A minimal in-memory PSR-16 cache for exercising the token provider's
 * cross-process caching path without pulling in a cache dependency.
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expiresAt: float}> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->store[$key] ?? null;
        if ($entry === null) {
            return $default;
        }
        if ($entry['expiresAt'] !== 0.0 && $entry['expiresAt'] < microtime(true)) {
            unset($this->store[$key]);

            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $expiresAt = 0.0;
        if (is_int($ttl)) {
            $expiresAt = microtime(true) + $ttl;
        } elseif ($ttl instanceof \DateInterval) {
            $expiresAt = (float) (new \DateTimeImmutable())->add($ttl)->getTimestamp();
        }
        $this->store[$key] = ['value' => $value, 'expiresAt' => $expiresAt];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }
}
