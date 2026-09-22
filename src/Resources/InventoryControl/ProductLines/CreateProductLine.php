<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\ProductLines;

use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateProductLine
{
    public function __construct(
        public ObjectId $id,
        public ?string $description = null,
        public ?RecordStatus $status = null,
        public ?ObjectReference $parent = null,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'id' => $this->id->value,
            'description' => $this->description,
            'status' => $this->status?->value,
            'parent' => $this->parent?->toWriteArray(),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
