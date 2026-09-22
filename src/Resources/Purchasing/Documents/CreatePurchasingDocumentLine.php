<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\Documents;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class CreatePurchasingDocumentLine
{
    /**
     * @param  string  $unit  The unit of measure name, such as "Each".
     * @param  Dimensions|null  $dimensions  Additional dimensions; the item, warehouse and location arguments take precedence.
     * @param  ObjectReference|null  $sourceDocument  The document that owns $sourceDocumentLine when converting.
     * @param  ObjectReference|null  $sourceDocumentLine  The line being converted, such as a purchase order line on a receipt.
     */
    public function __construct(
        public ObjectReference $item,
        public ObjectReference $warehouse,
        public ObjectReference $location,
        public string $unit,
        public Decimal $unitQuantity,
        public Decimal $unitPrice,
        public ?Dimensions $dimensions = null,
        public ?string $lineDescription = null,
        public ?string $memo = null,
        public ?ObjectReference $sourceDocument = null,
        public ?ObjectReference $sourceDocumentLine = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->unit, 'The line unit');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'unit' => $this->unit,
            'unitQuantity' => $this->unitQuantity->value,
            'unitPrice' => $this->unitPrice->value,
            'dimensions' => [
                ...($this->dimensions?->toWriteArray() ?? []),
                'item' => $this->item->toWriteArray(),
                'warehouse' => $this->warehouse->toWriteArray(),
                'location' => $this->location->toWriteArray(),
            ],
            'lineDescription' => $this->lineDescription,
            'memo' => $this->memo,
            'sourceDocument' => $this->sourceDocument?->toWriteArray(),
            'sourceDocumentLine' => $this->sourceDocumentLine?->toWriteArray(),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
