<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\LaborUnions;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class LaborUnion
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?string $name,
        public ?string $description,
        public ?RecordStatus $status,
        public ?ObjectReference $entity,
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
            name: ArrayReader::string($data, 'name'),
            description: ArrayReader::string($data, 'description'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            entity: ArrayReader::reference($data, 'entity'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
