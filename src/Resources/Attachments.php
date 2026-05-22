<?php

declare(strict_types=1);

namespace Tns\Epic\Resources;

use Psr\Http\Message\StreamInterface;
use Tns\Epic\Exceptions\ApiException;

/**
 * Attachments API — attachment files and their metadata.
 *
 * Service: `/epic/attachment/v2` — see spec/applied-epic-attachment-v2.yml
 */
final class Attachments extends Resource
{
    protected function service(): string
    {
        return '/epic/attachment/v2';
    }

    protected function collection(): string
    {
        return 'attachments';
    }

    /**
     * Download the binary file behind an attachment.
     *
     * Resolves the attachment record, then streams the document from the
     * `file.url` Applied returns on it.
     */
    public function download(string $id): StreamInterface
    {
        $attachment = $this->get($id);

        $url = $attachment['file']['url'] ?? null;
        if (!is_string($url) || $url === '') {
            throw new ApiException("Attachment {$id} has no downloadable file URL.");
        }

        return $this->client->getStream($url);
    }
}
