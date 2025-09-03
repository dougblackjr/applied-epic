<?php
namespace Tns\Epic\Resources;

use Tns\Epic\Http\BaseClient;

final class Quotes
{
    public function __construct(private BaseClient $client) {}

    /** GET /api/option_sets/{applicationType}/{applicationVersion} */
    public function optionSets(string $applicationType, string $applicationVersion): array
    {
        $path = sprintf('/api/option_sets/%s/%s', rawurlencode($applicationType), rawurlencode($applicationVersion));
        [$status, $headers, $body] = $this->client->request('GET', $path);
        return $this->decode($body);
    }

    private function decode(string $body): array
    {
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }
}
