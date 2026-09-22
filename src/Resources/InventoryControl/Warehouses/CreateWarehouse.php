<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\Warehouses;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateWarehouse
{
    public function __construct(
        public ObjectId $id,
        public string $name,
        public ObjectReference $location,
        public ?RecordStatus $status = null,
        public ?ObjectReference $parent = null,
        public ?ObjectReference $manager = null,
        public ?bool $replenishmentEnabled = null,
        public ?bool $negativeInventoryEnabled = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->name, 'The warehouse name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'id' => $this->id->value,
            'name' => $this->name,
            'location' => $this->location->toWriteArray(),
            'status' => $this->status?->value,
            'parent' => $this->parent?->toWriteArray(),
            'manager' => $this->manager?->toWriteArray(),
            'isReplenishmentEnabled' => $this->replenishmentEnabled,
            'enableNegativeInv' => $this->negativeInventoryEnabled,
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
