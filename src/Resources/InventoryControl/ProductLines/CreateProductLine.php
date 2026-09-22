<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\ProductLines;

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
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id->value,
            'description' => $this->description,
            'status' => $this->status?->value,
            'parent' => $this->parent?->toWriteArray(),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
