<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ChangeRequests;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\ObjectReference;

/**
 * Changes to an existing change request line, applied through the change request's PATCH.
 * Only the fields named by a call are sent; a null argument clears its field.
 */
final readonly class UpdateChangeRequestLine
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function quantity(?Decimal $quantity): self
    {
        return new self(['quantity' => $quantity?->value]);
    }

    public static function unitCost(?Decimal $unitCost): self
    {
        return new self(['unitCost' => $unitCost?->value]);
    }

    public static function unitPrice(?Decimal $unitPrice): self
    {
        return new self(['unitPrice' => $unitPrice?->value]);
    }

    public static function memo(?string $memo): self
    {
        return new self(['memo' => $memo]);
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

    public function withQuantity(?Decimal $quantity): self
    {
        return $this->with('quantity', $quantity?->value);
    }

    public function withExternalUnitOfMeasure(?string $unit): self
    {
        return $this->with('externalUOM', $unit);
    }

    public function withUnitCost(?Decimal $unitCost): self
    {
        return $this->with('unitCost', $unitCost?->value);
    }

    public function withCost(?Decimal $cost): self
    {
        return $this->with('cost', $cost?->value);
    }

    public function withPriceMarkupPercent(?Decimal $percent): self
    {
        return $this->with('priceMarkupPercent', $percent?->value);
    }

    public function withPriceMarkupAmount(?Decimal $amount): self
    {
        return $this->with('priceMarkupAmount', $amount?->value);
    }

    public function withUnitPrice(?Decimal $unitPrice): self
    {
        return $this->with('unitPrice', $unitPrice?->value);
    }

    public function withPrice(?Decimal $price): self
    {
        return $this->with('price', $price?->value);
    }

    public function withNumberOfProductionUnits(?Decimal $units): self
    {
        return $this->with('numberOfProductionUnits', $units?->value);
    }

    public function withWorkflowType(ChangeRequestWorkflowType $workflowType): self
    {
        return $this->with('workflowType', $workflowType->value);
    }

    public function withMemo(?string $memo): self
    {
        return $this->with('memo', $memo);
    }

    /** Writes the given dimensions; dimensions it leaves unset keep their values. */
    public function withDimensions(Dimensions $dimensions): self
    {
        return $this->with('dimensions', $dimensions->toWriteArray());
    }

    public function withGlAccount(?ObjectReference $glAccount): self
    {
        return $this->with('glAccount', $glAccount?->toWriteArray());
    }

    public function withProjectContract(?ObjectReference $projectContract): self
    {
        return $this->with('projectContract', $projectContract?->toWriteArray());
    }

    public function withProjectContractLine(?ObjectReference $projectContractLine): self
    {
        return $this->with('projectContractLine', $projectContractLine?->toWriteArray());
    }

    /** Sets a custom field, named with or without the `nsp::` prefix; null clears it. */
    public function withCustomField(string $name, mixed $value): self
    {
        return $this->withCustomFields((new CustomFields)->with($name, $value));
    }

    /** Sets custom fields; a null value clears its field. */
    public function withCustomFields(CustomFields $fields): self
    {
        $update = $this;

        foreach ($fields->toWriteArray() as $field => $value) {
            $update = $update->with($field, $value);
        }

        return $update;
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
