<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContractLines;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectKey;

/**
 * A price entry owned by a contract line (`construction/project-contract-line-entry`). Entries
 * have no endpoint of their own for writes; add or remove them through the line.
 */
final readonly class ProjectContractLineEntry
{
    public function __construct(
        public ObjectKey $key,
        public ?ProjectContractLineEntryWorkflowType $workflowType,
        public ?Decimal $quantity,
        public ?Decimal $unitPrice,
        public ?Decimal $price,
        public ?Decimal $priceMarkupPercent,
        public ?Decimal $priceMarkupAmount,
        public ?Decimal $linePrice,
        public ?string $externalUom,
        public ?string $memo,
        public ?LocalDate $priceEffectiveDate,
        public Dimensions $dimensions,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $workflowType = ArrayReader::string($data, 'workflowType');

        return new self(
            key: ArrayReader::key($data),
            workflowType: $workflowType === null ? null : ProjectContractLineEntryWorkflowType::tryFrom($workflowType),
            quantity: ArrayReader::decimal($data, 'quantity'),
            unitPrice: ArrayReader::decimal($data, 'unitPrice'),
            price: ArrayReader::decimal($data, 'price'),
            priceMarkupPercent: ArrayReader::decimal($data, 'priceMarkupPercent'),
            priceMarkupAmount: ArrayReader::decimal($data, 'priceMarkupAmount'),
            linePrice: ArrayReader::decimal($data, 'linePrice'),
            externalUom: ArrayReader::string($data, 'externalUOM'),
            memo: ArrayReader::string($data, 'memo'),
            priceEffectiveDate: ArrayReader::date($data, 'priceEffectiveDate'),
            dimensions: Dimensions::fromArray(ArrayReader::object($data['dimensions'] ?? null) ?? []),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
