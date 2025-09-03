<?php
namespace Tns\Epic\Exceptions;

final class EpicApiException extends \RuntimeException
{
    private int $status;
    private ?string $field = null;
    private ?string $information = null;
    private ?string $referUri = null;

    public static function fromHttp(int $status, string $body): self
    {
        $msg = 'Epic API error';
        $field = $information = $referUri = null;

        // Attempt to parse JSON error shapes commonly returned by Epic SDK
        $data = json_decode($body, true);
        if (is_array($data)) {
            $pieces = [];
            if (isset($data['message'])) $pieces[] = $data['message'];
            if (isset($data['field'])) { $pieces[] = 'field=' . $data['field']; $field = (string)$data['field']; }
            if (isset($data['information'])) { $pieces[] = 'information=' . $data['information']; $information = (string)$data['information']; }
            if (isset($data['refer_uri'])) { $pieces[] = 'refer_uri=' . $data['refer_uri']; $referUri = (string)$data['refer_uri']; }
            if ($pieces) {
                $msg = implode(' | ', $pieces);
            }
        } elseif (is_string($body) && $body !== '') {
            $msg = trim($body);
        }

        $e = new self("HTTP {$status}: " . $msg, $status);
        $e->status = $status;
        $e->field = $field;
        $e->information = $information;
        $e->referUri = $referUri;
        return $e;
    }

    public function getStatusCode(): int { return $this->status; }
    public function getField(): ?string { return $this->field; }
    public function getInformation(): ?string { return $this->information; }
    public function getReferUri(): ?string { return $this->referUri; }
}
