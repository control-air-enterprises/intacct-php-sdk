<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\Items;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

/**
 * Sage does not allow the item type or cost method to change after an item is created.
 */
final readonly class UpdateItem
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function name(string $name): self
    {
        Assert::notBlank($name, 'The item name');

        return new self(['name' => $name]);
    }

    public static function status(RecordStatus $status): self
    {
        return new self(['status' => $status->value]);
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

    public function withStatus(RecordStatus $status): self
    {
        return $this->with('status', $status->value);
    }

    public function withProductLine(?ObjectReference $productLine): self
    {
        return $this->with('productLine', $productLine?->toWriteArray());
    }

    public function withExtendedDescription(?string $description): self
    {
        return $this->with('extendedDescription', $description);
    }

    public function withPurchasingDescription(?string $description): self
    {
        return $this->with('poDescription', $description);
    }

    public function withSalesDescription(?string $description): self
    {
        return $this->with('soDescription', $description);
    }

    public function withStandardCost(?Decimal $standardCost): self
    {
        return $this->with('purchasing', ['standardCost' => $standardCost?->value]);
    }

    public function withBasePrice(?Decimal $basePrice): self
    {
        $sales = $this->changes['sales'] ?? [];

        return $this->with('sales', [...(is_array($sales) ? $sales : []), 'basePrice' => $basePrice?->value]);
    }

    public function withTaxable(bool $taxable): self
    {
        $sales = $this->changes['sales'] ?? [];

        return $this->with('sales', [...(is_array($sales) ? $sales : []), 'isTaxable' => $taxable]);
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
