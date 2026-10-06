<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ChangeRequests;

use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderExternalReference;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderInternalReference;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderSchedule;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class CreateChangeRequest
{
    /**
     * @param  list<CreateChangeRequestLine>  $lines  Created with the header.
     * @param  ObjectId|null  $id  Omit when the company numbers change requests automatically.
     * @param  ChangeRequestState|null  $state  Sage defaults to draft.
     * @param  ChangeRequestContractLineSource|null  $projectContractLineSource  Sage defaults to projectChangeOrder.
     */
    public function __construct(
        public ObjectReference $project,
        public LocalDate $changeRequestDate,
        public array $lines = [],
        public ?ObjectId $id = null,
        public ?string $description = null,
        public ?ChangeRequestState $state = null,
        public ?ObjectReference $changeRequestType = null,
        public ?ObjectReference $changeRequestStatus = null,
        public ?LocalDate $costEffectiveDate = null,
        public ?LocalDate $priceEffectiveDate = null,
        public ?ObjectReference $projectContract = null,
        public ?ObjectReference $projectContractLine = null,
        public ?ChangeRequestContractLineSource $projectContractLineSource = null,
        public ?ObjectReference $projectChangeOrder = null,
        public ?ObjectReference $attachment = null,
        public ?string $scope = null,
        public ?string $inclusions = null,
        public ?string $exclusions = null,
        public ?string $terms = null,
        public ?ChangeOrderSchedule $schedule = null,
        public ?ChangeOrderInternalReference $internalReference = null,
        public ?ChangeOrderExternalReference $externalReference = null,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'id' => $this->id?->value,
            'description' => $this->description,
            'project' => $this->project->toWriteArray(),
            'changeRequestDate' => $this->changeRequestDate->value,
            'changeRequestState' => $this->state?->value,
            'changeRequestType' => $this->changeRequestType?->toWriteArray(),
            'changeRequestStatus' => $this->changeRequestStatus?->toWriteArray(),
            'costEffectiveDate' => $this->costEffectiveDate?->value,
            'priceEffectiveDate' => $this->priceEffectiveDate?->value,
            'projectContract' => $this->projectContract?->toWriteArray(),
            'projectContractLine' => $this->projectContractLine?->toWriteArray(),
            'projectContractLineSource' => $this->projectContractLineSource?->value,
            'projectChangeOrder' => $this->projectChangeOrder?->toWriteArray(),
            'attachment' => $this->attachment?->toWriteArray(),
            'scope' => $this->scope,
            'inclusions' => $this->inclusions,
            'exclusions' => $this->exclusions,
            'terms' => $this->terms,
            'schedule' => $this->schedule?->toCreateArray(),
            'internalReference' => $this->internalReference?->toCreateArray(),
            'externalReference' => $this->externalReference?->toCreateArray(),
            'changeRequestLines' => $this->lines === [] ? null : array_map(
                static fn (CreateChangeRequestLine $line): array => $line->toArray(),
                $this->lines,
            ),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
