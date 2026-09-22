<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Attachments;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class AttachmentFolder
{
    /**
     * @param  ObjectReference|null  $parent  The parent folder; folders can be nested.
     */
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?string $description,
        public ?RecordStatus $status,
        public ?ObjectReference $parent,
        public ?ObjectReference $entity,
        public ?bool $hasSubfolders,
        public ?bool $hasAttachments,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            description: ArrayReader::string($data, 'description'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            parent: ArrayReader::reference($data, 'parent'),
            entity: ArrayReader::reference($data, 'entity'),
            hasSubfolders: ArrayReader::bool($data, 'hasSubfolders'),
            hasAttachments: ArrayReader::bool($data, 'hasAttachments'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
