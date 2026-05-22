<?php

declare(strict_types=1);

namespace Tns\Epic\Resources;

use Tns\Epic\Exceptions\AppliedEpicException;

/**
 * Activities API (Workflow Management) — activities for work tracking.
 *
 * Service: `/workflow-management/v1` — see spec/applied-epic-workflowManagement-v1-3.yml
 *
 * The modern API exposes no flat activity collection; activities are listed in
 * the context of a client. {@see list()} therefore requires a `client` filter.
 * A single activity is still addressable by id via {@see get()}.
 */
final class Activities extends Resource
{
    protected function service(): string
    {
        return '/workflow-management/v1';
    }

    protected function collection(): string
    {
        return 'activities';
    }

    /**
     * List activities for a client, paginating transparently.
     *
     * @param array<string, mixed> $filters Must include a `client` entry holding the client id.
     * @return iterable<int, array<string, mixed>>
     */
    public function list(array $filters = []): iterable
    {
        $clientId = $filters['client'] ?? null;
        unset($filters['client']);

        if (!is_string($clientId) || $clientId === '') {
            throw new AppliedEpicException(
                "Activities::list() requires a 'client' filter holding the client id."
            );
        }

        $path = $this->service() . '/clients/' . rawurlencode($clientId) . '/activities';

        return $this->client->paginate($path, $this->collection(), $filters);
    }
}
