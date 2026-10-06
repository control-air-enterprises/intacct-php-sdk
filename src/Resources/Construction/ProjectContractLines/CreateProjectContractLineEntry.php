<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContractLines;

use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;

/** A price entry written inside a contract line create or update. */
final readonly class CreateProjectContractLineEntry
{
    /**
     * @param  ProjectContractLineEntryWorkflowType|null  $workflowType  Sage defaults to `original`.
     * @param  string|null  $externalUom  The customer-facing unit of measure label.
     */
    public function __construct(
        public ?ProjectContractLineEntryWorkflowType $workflowType = null,
        public ?Decimal $quantity = null,
        public ?Decimal $unitPrice = null,
        public ?Decimal $price = null,
        public ?Decimal $priceMarkupPercent = null,
        public ?Decimal $priceMarkupAmount = null,
        public ?string $externalUom = null,
        public ?string $memo = null,
        public ?LocalDate $priceEffectiveDate = null,
        public ?Dimensions $dimensions = null,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $dimensions = $this->dimensions?->toWriteArray() ?? [];

        $payload = array_filter([
            'workflowType' => $this->workflowType?->value,
            'quantity' => $this->quantity?->value,
            'unitPrice' => $this->unitPrice?->value,
            'price' => $this->price?->value,
            'priceMarkupPercent' => $this->priceMarkupPercent?->value,
            'priceMarkupAmount' => $this->priceMarkupAmount?->value,
            'externalUOM' => $this->externalUom,
            'memo' => $this->memo,
            'priceEffectiveDate' => $this->priceEffectiveDate?->value,
            'dimensions' => $dimensions === [] ? null : $dimensions,
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
