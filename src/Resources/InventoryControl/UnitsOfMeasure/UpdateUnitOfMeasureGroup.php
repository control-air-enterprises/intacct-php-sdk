<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\ValueObjects\CustomFields;
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

    /** Starts a change set with one custom field, named with or without the `nsp::` prefix; null clears it. */
    public static function customField(string $name, mixed $value): self
    {
        return self::customFields((new CustomFields)->with($name, $value));
    }

    /** Starts a change set with custom fields; a null value clears its field. */
    public static function customFields(CustomFields $fields): self
    {
        $changes = $fields->toWriteArray();

        if ($changes === []) {
            throw new InvalidArgument('At least one custom field is required.');
        }

        return new self($changes);
    }

    public function withAbbreviation(?string $abbreviation): self
    {
        return new self([...$this->changes, 'abbreviation' => $abbreviation]);
    }

    /** Sets a custom field, named with or without the `nsp::` prefix; null clears it. */
    public function withCustomField(string $name, mixed $value): self
    {
        return $this->withCustomFields((new CustomFields)->with($name, $value));
    }

    /** Sets custom fields; a null value clears its field. */
    public function withCustomFields(CustomFields $fields): self
    {
        return new self([...$this->changes, ...$fields->toWriteArray()]);
    }

    /** @return non-empty-array<string, mixed> */
    public function toArray(): array
    {
        return $this->changes;
    }
}
