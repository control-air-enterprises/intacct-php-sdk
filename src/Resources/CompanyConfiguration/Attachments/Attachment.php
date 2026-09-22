<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Attachments;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class Attachment
{
    /**
     * @param  list<AttachedFile>  $files  Empty on query results; read the attachment to load its files.
     */
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?string $description,
        public ?ObjectReference $folder,
        public ?ObjectReference $entity,
        public array $files,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            name: ArrayReader::requiredString($data, 'name'),
            description: ArrayReader::string($data, 'description'),
            folder: ArrayReader::reference($data, 'folder'),
            entity: ArrayReader::reference($data, 'entity'),
            files: array_map(
                AttachedFile::fromArray(...),
                ArrayReader::list($data, 'files'),
            ),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
