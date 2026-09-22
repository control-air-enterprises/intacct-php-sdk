<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class UnitOfMeasureGroup
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $baseUnit,
        public ?string $abbreviation,
        public ?ObjectReference $defaultInventoryUnit,
        public ?ObjectReference $defaultPurchasingUnit,
        public ?ObjectReference $defaultOrderEntryUnit,
        public ?bool $systemGenerated,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $defaults = ArrayReader::object($data['defaults'] ?? null) ?? [];

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            baseUnit: ArrayReader::requiredString($data, 'baseUnit'),
            abbreviation: ArrayReader::string($data, 'abbreviation'),
            defaultInventoryUnit: ArrayReader::reference($defaults, 'inventory'),
            defaultPurchasingUnit: ArrayReader::reference($defaults, 'purchaseOrder'),
            defaultOrderEntryUnit: ArrayReader::reference($defaults, 'orderEntry'),
            systemGenerated: ArrayReader::bool($data, 'isSystemGenerated'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
