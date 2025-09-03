<?php
namespace Tns\Epic\Resources;

use Tns\Epic\Http\BaseClient;

final class Logins
{
    public function __construct(private BaseClient $client) {}

    /** PATCH /api/logins - partial update semantics */
    public function patch(array $payload): array
    {
        [$status, $headers, $body] = $this->client->request('PATCH', '/api/logins', json: $payload);
        return $this->decode($body);
    }

    private function decode(string $body): array
    {
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }
}
