<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\Items;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateItem
{
    public function __construct(
        public ObjectId $id,
        public string $name,
        public ItemType $itemType,
        public CostMethod $costMethod,
        public ?RecordStatus $status = null,
        public ?ObjectReference $productLine = null,
        public ?ObjectReference $unitOfMeasureGroup = null,
        public ?string $extendedDescription = null,
        public ?string $purchasingDescription = null,
        public ?string $salesDescription = null,
        public ?Decimal $standardCost = null,
        public ?Decimal $basePrice = null,
        public ?bool $taxable = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->name, 'The item name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $sales = array_filter([
            'basePrice' => $this->basePrice?->value,
            'isTaxable' => $this->taxable,
        ], static fn (mixed $value): bool => $value !== null);

        $payload = array_filter([
            'id' => $this->id->value,
            'name' => $this->name,
            'itemType' => $this->itemType->value,
            'costMethod' => $this->costMethod->value,
            'status' => $this->status?->value,
            'productLine' => $this->productLine?->toWriteArray(),
            'unitOfMeasureGroup' => $this->unitOfMeasureGroup?->toWriteArray(),
            'extendedDescription' => $this->extendedDescription,
            'poDescription' => $this->purchasingDescription,
            'soDescription' => $this->salesDescription,
            'purchasing' => $this->standardCost === null ? null : ['standardCost' => $this->standardCost->value],
            'sales' => $sales === [] ? null : $sales,
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
