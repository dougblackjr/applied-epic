<?php

declare(strict_types=1);

namespace Tns\Epic\Resources;

use Tns\Epic\Client;

/**
 * Base class for one Applied microservice collection.
 *
 * A subclass declares the service base path and the collection name; the
 * standard `list()` and `get()` operations are derived from those. Records
 * are returned as plain associative arrays — the consuming application is
 * free to map them onto its own models.
 */
abstract class Resource
{
    public function __construct(protected readonly Client $client)
    {
    }

    /** The microservice base path, e.g. `/epic/policy/v2`. */
    abstract protected function service(): string;

    /** The collection segment, which is also the `_embedded` key, e.g. `policies`. */
    abstract protected function collection(): string;

    /**
     * Retrieve every record in the collection, paginating transparently.
     *
     * @param array<string, mixed> $filters Query filters; `limit` sets the page size.
     * @return iterable<int, array<string, mixed>>
     */
    public function list(array $filters = []): iterable
    {
        return $this->client->paginate($this->collectionPath(), $this->collection(), $filters);
    }

    /**
     * Retrieve a single record by its opaque id.
     *
     * @param array<string, mixed> $query Extra query params, e.g. `['embed' => 'client']`.
     * @return array<string, mixed>
     */
    public function get(string $id, array $query = []): array
    {
        return $this->client->get($this->collectionPath() . '/' . rawurlencode($id), $query);
    }

    protected function collectionPath(): string
    {
        return rtrim($this->service(), '/') . '/' . $this->collection();
    }
}
