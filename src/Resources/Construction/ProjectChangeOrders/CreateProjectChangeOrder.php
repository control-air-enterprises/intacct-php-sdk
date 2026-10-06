<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectChangeOrders;

use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateProjectChangeOrder
{
    /**
     * @param  ObjectId|null  $id  Omit when the company numbers project change orders automatically.
     * @param  ProjectChangeOrderState|null  $state  Sage defaults to draft.
     * @param  ObjectReference|null  $sendToContact  The contact the change order is sent to.
     */
    public function __construct(
        public ObjectReference $project,
        public LocalDate $projectChangeOrderDate,
        public ?ObjectId $id = null,
        public ?string $description = null,
        public ?ProjectChangeOrderState $state = null,
        public ?RecordStatus $status = null,
        public ?LocalDate $priceEffectiveDate = null,
        public ?ObjectReference $projectContract = null,
        public ?ObjectReference $projectContractLine = null,
        public ?ObjectReference $changeRequestStatus = null,
        public ?ObjectReference $item = null,
        public ?ObjectReference $sendToContact = null,
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
            'projectChangeOrderDate' => $this->projectChangeOrderDate->value,
            'state' => $this->state?->value,
            'status' => $this->status?->value,
            'priceEffectiveDate' => $this->priceEffectiveDate?->value,
            'projectContract' => $this->projectContract?->toWriteArray(),
            'projectContractLine' => $this->projectContractLine?->toWriteArray(),
            'changeRequestStatus' => $this->changeRequestStatus?->toWriteArray(),
            'item' => $this->item?->toWriteArray(),
            'sendToContact' => $this->sendToContact?->toWriteArray(),
            'attachment' => $this->attachment?->toWriteArray(),
            'scope' => $this->scope,
            'inclusions' => $this->inclusions,
            'exclusions' => $this->exclusions,
            'terms' => $this->terms,
            'schedule' => $this->schedule?->toCreateArray(),
            'internalReference' => $this->internalReference?->toCreateArray(),
            'externalReference' => $this->externalReference?->toCreateArray(),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
