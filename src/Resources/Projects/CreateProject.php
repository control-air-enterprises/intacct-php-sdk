<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateProject
{
    public function __construct(
        public ObjectId $id,
        public string $name,
        public ?string $description = null,
        public ?string $currency = null,
        public ?string $category = null,
        public ?RecordStatus $status = null,
        public ?LocalDate $startDate = null,
        public ?LocalDate $endDate = null,
        public ?ProjectBudget $budget = null,
        public ?Decimal $contractAmount = null,
        public ?string $billingType = null,
        public ?ObjectReference $projectStatus = null,
        public ?ObjectReference $projectType = null,
        public ?ObjectReference $parent = null,
        public ?ObjectReference $customer = null,
        public ?ObjectReference $manager = null,
        public ?ObjectReference $department = null,
        public ?ObjectReference $location = null,
        public ?ObjectReference $class = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->name, 'The project name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'id' => $this->id->value,
            'name' => $this->name,
            'description' => $this->description,
            'projectCurrency' => $this->currency,
            'category' => $this->category,
            'status' => $this->status?->value,
            'startDate' => $this->startDate?->value,
            'endDate' => $this->endDate?->value,
            'budget' => $this->budget?->toArray(),
            'contractAmount' => $this->contractAmount?->value,
            'billingType' => $this->billingType,
            'projectStatus' => $this->projectStatus?->toWriteArray(),
            'projectType' => $this->projectType?->toWriteArray(),
            'parent' => $this->parent?->toWriteArray(),
            'customer' => $this->customer?->toWriteArray(),
            'manager' => $this->manager?->toWriteArray(),
            'department' => $this->department?->toWriteArray(),
            'location' => $this->location?->toWriteArray(),
            'class' => $this->class?->toWriteArray(),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
