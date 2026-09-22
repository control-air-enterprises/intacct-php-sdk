<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure;

use ControlAir\Intacct\ValueObjects\Decimal;

final readonly class UpdateUnitOfMeasure
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function conversionFactor(Decimal $conversionFactor): self
    {
        return new self(['conversionFactor' => $conversionFactor->value]);
    }

    public static function abbreviation(?string $abbreviation): self
    {
        return new self(['abbreviation' => $abbreviation]);
    }

    public function withConversionFactor(Decimal $conversionFactor): self
    {
        return $this->with('conversionFactor', $conversionFactor->value);
    }

    public function withAbbreviation(?string $abbreviation): self
    {
        return $this->with('abbreviation', $abbreviation);
    }

    public function withDecimalPlaces(?int $decimalPlaces): self
    {
        return $this->with('numberOfDecimalPlaces', $decimalPlaces);
    }

    /** @return non-empty-array<string, mixed> */
    public function toArray(): array
    {
        return $this->changes;
    }

    private function with(string $field, mixed $value): self
    {
        return new self([...$this->changes, $field => $value]);
    }
}
