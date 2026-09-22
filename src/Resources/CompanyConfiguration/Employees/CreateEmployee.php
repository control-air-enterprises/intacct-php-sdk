<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Employees;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use ControlAir\Intacct\ValueObjects\SensitiveString;

final readonly class CreateEmployee
{
    public function __construct(
        public ObjectId $id,
        public string $name,
        public ?string $jobTitle = null,
        public ?RecordStatus $status = null,
        public ?LocalDate $birthDate = null,
        public ?LocalDate $startDate = null,
        public ?LocalDate $endDate = null,
        public ?ObjectReference $manager = null,
        public ?ObjectReference $location = null,
        public ?ObjectReference $department = null,
        public ?ObjectReference $employeeType = null,
        public ?ObjectReference $primaryContact = null,
        public ?ObjectReference $class = null,
        public ?string $defaultCurrency = null,
        public ?SensitiveString $ssn = null,
        public ?bool $placeholderResource = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->name, 'The employee name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'id' => $this->id->value,
            'name' => $this->name,
            'jobTitle' => $this->jobTitle,
            'status' => $this->status?->value,
            'birthDate' => $this->birthDate?->value,
            'startDate' => $this->startDate?->value,
            'endDate' => $this->endDate?->value,
            'manager' => $this->manager?->toWriteArray(),
            'location' => $this->location?->toWriteArray(),
            'department' => $this->department?->toWriteArray(),
            'employeeType' => $this->employeeType?->toWriteArray(),
            'primaryContact' => $this->primaryContact?->toWriteArray(),
            'class' => $this->class?->toWriteArray(),
            'defaultCurrency' => $this->defaultCurrency,
            'SSN' => $this->ssn?->reveal(),
            'isPlaceholderResource' => $this->placeholderResource,
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
