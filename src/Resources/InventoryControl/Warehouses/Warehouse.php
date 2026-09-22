<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\Warehouses;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class Warehouse
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?RecordStatus $status,
        public ?ObjectReference $location,
        public ?ObjectReference $parent,
        public ?ObjectReference $manager,
        public ?bool $replenishmentEnabled,
        public ?bool $negativeInventoryEnabled,
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
            name: ArrayReader::requiredString($data, 'name'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            location: ArrayReader::reference($data, 'location'),
            parent: ArrayReader::reference($data, 'parent'),
            manager: ArrayReader::reference($data, 'manager'),
            replenishmentEnabled: ArrayReader::bool($data, 'isReplenishmentEnabled'),
            negativeInventoryEnabled: ArrayReader::bool($data, 'enableNegativeInv'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
