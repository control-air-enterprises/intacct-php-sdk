<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure;

use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class CreateUnitOfMeasure
{
    public function __construct(
        public ObjectId $id,
        public ObjectReference $group,
        public Decimal $conversionFactor,
        public ?string $abbreviation = null,
        public ?int $decimalPlaces = null,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'id' => $this->id->value,
            'parent' => $this->group->toWriteArray(),
            'conversionFactor' => $this->conversionFactor->value,
            'abbreviation' => $this->abbreviation,
            'numberOfDecimalPlaces' => $this->decimalPlaces,
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
