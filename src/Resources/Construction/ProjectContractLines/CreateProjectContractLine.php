<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContractLines;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\ProjectContractSchedule;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateProjectContractLine
{
    /**
     * @param  ObjectId  $id  Set once on create; Sage does not allow changing it afterwards.
     * @param  ProjectContractSchedule|null  $schedule  Only its non-null dates are sent.
     * @param  list<CreateProjectContractLineEntry>  $entries  Price entries, such as the original contract price.
     */
    public function __construct(
        public ObjectReference $projectContract,
        public ObjectId $id,
        public string $name,
        public ?string $description = null,
        public ?ObjectReference $parent = null,
        public ?LocalDate $contractLineDate = null,
        public ?ObjectReference $glAccount = null,
        public ?Decimal $retainagePercentage = null,
        public ?bool $billable = null,
        public ?bool $excludeFromGlBudget = null,
        public ?ProjectContractLineBillingType $billingType = null,
        public ?ProjectContractLineMaximumBilling $maximumBilling = null,
        public ?Decimal $maximumBillingAmount = null,
        public ?bool $summarizeBill = null,
        public ?string $scope = null,
        public ?string $inclusions = null,
        public ?string $exclusions = null,
        public ?string $terms = null,
        public ?RecordStatus $status = null,
        public ?ProjectContractSchedule $schedule = null,
        public ?string $internalReferenceNumber = null,
        public ?string $externalReferenceNumber = null,
        public ?Dimensions $dimensions = null,
        public array $entries = [],
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->name, 'The project contract line name');

        if ($this->maximumBilling === ProjectContractLineMaximumBilling::SpecifiedAmount && $this->maximumBillingAmount === null) {
            throw new InvalidArgument('A specified-amount maximum billing requires a maximum billing amount.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $billingSetup = array_filter([
            'billingType' => $this->billingType?->value,
            'maximumBilling' => $this->maximumBilling?->value,
            'maximumBillingAmount' => $this->maximumBillingAmount?->value,
            'summarizeBill' => $this->summarizeBill,
        ], static fn (mixed $value): bool => $value !== null);
        $schedule = $this->schedule?->toArray() ?? [];
        $dimensions = $this->dimensions?->toWriteArray() ?? [];

        $payload = array_filter([
            'projectContract' => $this->projectContract->toWriteArray(),
            'id' => $this->id->value,
            'name' => $this->name,
            'description' => $this->description,
            'parent' => $this->parent?->toWriteArray(),
            'contractLineDate' => $this->contractLineDate?->value,
            'glAccount' => $this->glAccount?->toWriteArray(),
            'retainagePercentage' => $this->retainagePercentage?->value,
            'isBillable' => $this->billable,
            'excludeFromGLBudget' => $this->excludeFromGlBudget,
            'billingSetup' => $billingSetup === [] ? null : $billingSetup,
            'scope' => $this->scope,
            'inclusions' => $this->inclusions,
            'exclusions' => $this->exclusions,
            'terms' => $this->terms,
            'status' => $this->status?->value,
            'schedule' => $schedule === [] ? null : $schedule,
            'internalReference' => $this->internalReferenceNumber === null ? null : ['referenceNumber' => $this->internalReferenceNumber],
            'externalReference' => $this->externalReferenceNumber === null ? null : ['referenceNumber' => $this->externalReferenceNumber],
            'dimensions' => $dimensions === [] ? null : $dimensions,
            'projectContractLineEntries' => $this->entries === [] ? null : array_map(
                static fn (CreateProjectContractLineEntry $entry): array => $entry->toArray(),
                $this->entries,
            ),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
