<?php
namespace Tns\Epic\Resources;

use Tns\Epic\Http\BaseClient;

final class Persons
{
    public function __construct(private BaseClient $client) {}

    /** POST /api/person_search */
    public function search(array $filter): array
    {
        [$status, $headers, $body] = $this->client->request('POST', '/api/person_search', json: $filter);
        return $this->decode($body);
    }

    private function decode(string $body): array
    {
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }
}
