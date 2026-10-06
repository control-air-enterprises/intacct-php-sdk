<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ChangeRequests;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

/**
 * A cost-code level line of a change request: its project, task and cost type are in $dimensions.
 */
final readonly class ChangeRequestLine
{
    /**
     * @param  Decimal|null  $linePrice  Read-only; computed by Sage.
     * @param  ObjectReference|null  $projectChangeOrder  Read-only; the change order the line rolls up to.
     * @param  ObjectReference|null  $projectEstimate  Read-only.
     */
    public function __construct(
        public ObjectKey $key,
        public ?int $lineNumber,
        public ?ObjectReference $changeRequest,
        public ?Decimal $quantity,
        public ?string $externalUnitOfMeasure,
        public ?Decimal $unitCost,
        public ?Decimal $cost,
        public ?Decimal $priceMarkupPercent,
        public ?Decimal $priceMarkupAmount,
        public ?Decimal $unitPrice,
        public ?Decimal $price,
        public ?Decimal $linePrice,
        public ?Decimal $numberOfProductionUnits,
        public ?string $productionUnitDescription,
        public ?ChangeRequestWorkflowType $workflowType,
        public ?string $memo,
        public Dimensions $dimensions,
        public ?ObjectReference $glAccount,
        public ?ObjectReference $projectContract,
        public ?ObjectReference $projectContractLine,
        public ?ObjectReference $projectChangeOrder,
        public ?ObjectReference $projectEstimate,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $workflowType = ArrayReader::string($data, 'workflowType');

        return new self(
            key: ArrayReader::key($data),
            lineNumber: ArrayReader::int($data, 'lineNo'),
            changeRequest: ArrayReader::reference($data, 'changeRequest'),
            quantity: ArrayReader::decimal($data, 'quantity'),
            externalUnitOfMeasure: ArrayReader::string($data, 'externalUOM'),
            unitCost: ArrayReader::decimal($data, 'unitCost'),
            cost: ArrayReader::decimal($data, 'cost'),
            priceMarkupPercent: ArrayReader::decimal($data, 'priceMarkupPercent'),
            priceMarkupAmount: ArrayReader::decimal($data, 'priceMarkupAmount'),
            unitPrice: ArrayReader::decimal($data, 'unitPrice'),
            price: ArrayReader::decimal($data, 'price'),
            linePrice: ArrayReader::decimal($data, 'linePrice'),
            numberOfProductionUnits: ArrayReader::decimal($data, 'numberOfProductionUnits'),
            productionUnitDescription: ArrayReader::string($data, 'productionUnitDescription'),
            workflowType: $workflowType === null ? null : ChangeRequestWorkflowType::tryFrom($workflowType),
            memo: ArrayReader::string($data, 'memo'),
            dimensions: Dimensions::fromArray(ArrayReader::object($data['dimensions'] ?? null) ?? []),
            glAccount: ArrayReader::reference($data, 'glAccount'),
            projectContract: ArrayReader::reference($data, 'projectContract'),
            projectContractLine: ArrayReader::reference($data, 'projectContractLine'),
            projectChangeOrder: ArrayReader::reference($data, 'projectChangeOrder'),
            projectEstimate: ArrayReader::reference($data, 'projectEstimate'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
