<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContracts;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateProjectContract
{
    /**
     * @param  ObjectId  $id  Set once on create; Sage does not allow changing it afterwards.
     * @param  ProjectContractSchedule|null  $schedule  Only its non-null dates are sent.
     */
    public function __construct(
        public ObjectId $id,
        public string $name,
        public ?ObjectReference $project = null,
        public ?ObjectReference $customer = null,
        public ?LocalDate $contractDate = null,
        public ?string $description = null,
        public ?ObjectReference $projectContractType = null,
        public ?ObjectReference $location = null,
        public ?bool $billable = null,
        public ?bool $excludeFromWipReporting = null,
        public ?string $scope = null,
        public ?string $inclusions = null,
        public ?string $exclusions = null,
        public ?string $terms = null,
        public ?RecordStatus $status = null,
        public ?ProjectContractSchedule $schedule = null,
        public ?string $internalReferenceNumber = null,
        public ?string $externalReferenceNumber = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->name, 'The project contract name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $schedule = $this->schedule?->toArray() ?? [];

        $payload = array_filter([
            'id' => $this->id->value,
            'name' => $this->name,
            'description' => $this->description,
            'contractDate' => $this->contractDate?->value,
            'project' => $this->project?->toWriteArray(),
            'customer' => $this->customer?->toWriteArray(),
            'projectContractType' => $this->projectContractType?->toWriteArray(),
            'location' => $this->location?->toWriteArray(),
            'isBillable' => $this->billable,
            'excludeFromWIPReporting' => $this->excludeFromWipReporting,
            'scope' => $this->scope,
            'inclusions' => $this->inclusions,
            'exclusions' => $this->exclusions,
            'terms' => $this->terms,
            'status' => $this->status?->value,
            'schedule' => $schedule === [] ? null : $schedule,
            'internalReference' => $this->internalReferenceNumber === null ? null : ['referenceNumber' => $this->internalReferenceNumber],
            'externalReference' => $this->externalReferenceNumber === null ? null : ['referenceNumber' => $this->externalReferenceNumber],
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
