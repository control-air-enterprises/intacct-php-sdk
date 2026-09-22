<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\Items;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class Item
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?RecordStatus $status,
        public ?ItemType $itemType,
        public ?CostMethod $costMethod,
        public ?ObjectReference $productLine,
        public ?ObjectReference $unitOfMeasureGroup,
        public ?string $extendedDescription,
        public ?string $purchasingDescription,
        public ?string $salesDescription,
        public ?Decimal $standardCost,
        public ?Decimal $basePrice,
        public ?bool $taxable,
        public ?Decimal $quantityOnHand,
        public ?Decimal $quantityOnOrder,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');
        $itemType = ArrayReader::string($data, 'itemType');
        $costMethod = ArrayReader::string($data, 'costMethod');
        $purchasing = ArrayReader::object($data['purchasing'] ?? null) ?? [];
        $sales = ArrayReader::object($data['sales'] ?? null) ?? [];

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            name: ArrayReader::requiredString($data, 'name'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            itemType: $itemType === null ? null : ItemType::tryFrom($itemType),
            costMethod: $costMethod === null ? null : CostMethod::tryFrom($costMethod),
            productLine: ArrayReader::reference($data, 'productLine'),
            unitOfMeasureGroup: ArrayReader::reference($data, 'unitOfMeasureGroup'),
            extendedDescription: ArrayReader::string($data, 'extendedDescription'),
            purchasingDescription: ArrayReader::string($data, 'poDescription'),
            salesDescription: ArrayReader::string($data, 'soDescription'),
            standardCost: ArrayReader::decimal($purchasing, 'standardCost'),
            basePrice: ArrayReader::decimal($sales, 'basePrice'),
            taxable: ArrayReader::bool($sales, 'isTaxable'),
            quantityOnHand: ArrayReader::decimal($data, 'quantityOnHand'),
            quantityOnOrder: ArrayReader::decimal($data, 'quantityOnOrder'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
