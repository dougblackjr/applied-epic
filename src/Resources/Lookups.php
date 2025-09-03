<?php
namespace Tns\Epic\Resources;

use Tns\Epic\Http\BaseClient;

final class Lookups
{
    public function __construct(private BaseClient $client) {}

    /** GET /api/lookup_search -> array<string> */
    public function all(): array
    {
        [$status, $headers, $body] = $this->client->request('GET', '/api/lookup_search');
        return $this->decode($body);
    }

    /** POST /api/lookup_search/{type} -> list of dicts (code/description/etc.) */
    public function values(string $type, array $payload = []): array
    {
        [$status, $headers, $body] = $this->client->request('POST', "/api/lookup_search/{$type}", json: $payload);
        return $this->decode($body);
    }

    private function decode(string $body): array
    {
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }
}
