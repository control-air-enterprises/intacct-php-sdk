<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\ObjectId;

final readonly class CreateUnitOfMeasureGroup
{
    public function __construct(
        public ObjectId $id,
        public string $baseUnit,
        public ?string $abbreviation = null,
    ) {
        Assert::notBlank($this->baseUnit, 'The base unit');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id->value,
            'baseUnit' => $this->baseUnit,
            'abbreviation' => $this->abbreviation,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
