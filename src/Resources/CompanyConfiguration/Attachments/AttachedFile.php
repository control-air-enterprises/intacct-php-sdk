<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Attachments;

use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

/**
 * A file stored in an attachment (`company-config/file`), as returned by Sage.
 *
 * Sage returns the content base64-encoded. The spec calls the format `base64zip`, but its
 * examples decode to the raw text; see AttachmentFile for the open question.
 */
final readonly class AttachedFile
{
    /**
     * @param  int|null  $size  The file size in bytes.
     * @param  string|null  $data  The base64 content, when the response included it.
     */
    public function __construct(
        public ObjectKey $key,
        public ?string $name,
        public ?int $size,
        public ?string $externalStorageId,
        public ?ObjectReference $attachment,
        public ?string $href,
        #[\SensitiveParameter]
        private ?string $data = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key: ArrayReader::key($data),
            name: ArrayReader::string($data, 'name'),
            size: ArrayReader::int($data, 'size'),
            externalStorageId: ArrayReader::string($data, 'externalStorageId'),
            attachment: ArrayReader::reference($data, 'attachment'),
            href: ArrayReader::string($data, 'href'),
            data: ArrayReader::string($data, 'data'),
        );
    }

    public function hasData(): bool
    {
        return $this->data !== null;
    }

    public function base64Data(): ?string
    {
        return $this->data;
    }

    /** The decoded file bytes, or null when the response did not include the content. */
    public function contents(): ?string
    {
        if ($this->data === null) {
            return null;
        }

        $contents = base64_decode($this->data, true);

        if ($contents === false) {
            throw new MappingException(sprintf('The data of attachment file "%s" is not valid base64.', $this->key->value));
        }

        return $contents;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'size' => $this->size,
            'externalStorageId' => $this->externalStorageId,
            'attachment' => $this->attachment,
            'href' => $this->href,
            'data' => $this->data === null ? null : '[redacted]',
        ];
    }
}
