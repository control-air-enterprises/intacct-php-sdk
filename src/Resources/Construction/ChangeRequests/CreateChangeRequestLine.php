<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ChangeRequests;

use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class CreateChangeRequestLine
{
    /**
     * @param  ObjectReference  $project  The cost code's project; with $task and $costType it identifies the cost code.
     * @param  Dimensions|null  $dimensions  Additional dimensions; the project, task and cost type arguments take precedence.
     *                                       Sage derives the line location, so leave it unset.
     * @param  ChangeRequestWorkflowType|null  $workflowType  Sage defaults to none.
     */
    public function __construct(
        public ObjectReference $project,
        public ObjectReference $task,
        public ObjectReference $costType,
        public ?Decimal $quantity = null,
        public ?string $externalUnitOfMeasure = null,
        public ?Decimal $unitCost = null,
        public ?Decimal $cost = null,
        public ?Decimal $priceMarkupPercent = null,
        public ?Decimal $priceMarkupAmount = null,
        public ?Decimal $unitPrice = null,
        public ?Decimal $price = null,
        public ?Decimal $numberOfProductionUnits = null,
        public ?ChangeRequestWorkflowType $workflowType = null,
        public ?string $memo = null,
        public ?ObjectReference $glAccount = null,
        public ?ObjectReference $projectContract = null,
        public ?ObjectReference $projectContractLine = null,
        public ?Dimensions $dimensions = null,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'quantity' => $this->quantity?->value,
            'externalUOM' => $this->externalUnitOfMeasure,
            'unitCost' => $this->unitCost?->value,
            'cost' => $this->cost?->value,
            'priceMarkupPercent' => $this->priceMarkupPercent?->value,
            'priceMarkupAmount' => $this->priceMarkupAmount?->value,
            'unitPrice' => $this->unitPrice?->value,
            'price' => $this->price?->value,
            'numberOfProductionUnits' => $this->numberOfProductionUnits?->value,
            'workflowType' => $this->workflowType?->value,
            'memo' => $this->memo,
            'glAccount' => $this->glAccount?->toWriteArray(),
            'projectContract' => $this->projectContract?->toWriteArray(),
            'projectContractLine' => $this->projectContractLine?->toWriteArray(),
            'dimensions' => [
                ...($this->dimensions?->toWriteArray() ?? []),
                'project' => $this->project->toWriteArray(),
                'task' => $this->task->toWriteArray(),
                'costType' => $this->costType->toWriteArray(),
            ],
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
