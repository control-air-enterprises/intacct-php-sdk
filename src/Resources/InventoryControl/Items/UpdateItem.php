<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\Items;

use ControlAir\Intacct\Support\Assert;
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
