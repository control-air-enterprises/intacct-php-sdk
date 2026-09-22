<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure;

use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class UpdateUnitOfMeasureGroup
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function abbreviation(?string $abbreviation): self
    {
        return new self(['abbreviation' => $abbreviation]);
    }

    public static function defaults(
        ?ObjectReference $inventory = null,
        ?ObjectReference $purchaseOrder = null,
        ?ObjectReference $orderEntry = null,
    ): self {
        return new self(['defaults' => array_filter([
            'inventory' => $inventory?->toWriteArray(),
            'purchaseOrder' => $purchaseOrder?->toWriteArray(),
            'orderEntry' => $orderEntry?->toWriteArray(),
        ], static fn (?array $value): bool => $value !== null)]);
    }

    public function withAbbreviation(?string $abbreviation): self
    {
        return new self([...$this->changes, 'abbreviation' => $abbreviation]);
    }

    /** @return non-empty-array<string, mixed> */
    public function toArray(): array
    {
        return $this->changes;
    }
}
