<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class Project
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?string $description,
        public ?string $currency,
        public ?string $category,
        public ?RecordStatus $status,
        public ?LocalDate $startDate,
        public ?LocalDate $endDate,
        public ?ProjectBudget $budget,
        public ?Decimal $contractAmount,
        public ?Decimal $actualAmount,
        public ?string $billingType,
        public ?ObjectReference $projectStatus,
        public ?ObjectReference $projectType,
        public ?ObjectReference $parent,
        public ?ObjectReference $customer,
        public ?ObjectReference $manager,
        public ?ObjectReference $department,
        public ?ObjectReference $location,
        public ?ObjectReference $class,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $budget = ArrayReader::object($data['budget'] ?? null);
        $status = ArrayReader::string($data, 'status');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            name: ArrayReader::requiredString($data, 'name'),
            description: ArrayReader::string($data, 'description'),
            currency: ArrayReader::string($data, 'projectCurrency'),
            category: ArrayReader::string($data, 'category'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            startDate: ArrayReader::date($data, 'startDate'),
            endDate: ArrayReader::date($data, 'endDate'),
            budget: $budget === null ? null : ProjectBudget::fromArray($budget),
            contractAmount: ArrayReader::decimal($data, 'contractAmount'),
            actualAmount: ArrayReader::decimal($data, 'actualAmount'),
            billingType: ArrayReader::string($data, 'billingType'),
            projectStatus: ArrayReader::reference($data, 'projectStatus'),
            projectType: ArrayReader::reference($data, 'projectType'),
            parent: ArrayReader::reference($data, 'parent'),
            customer: ArrayReader::reference($data, 'customer'),
            manager: ArrayReader::reference($data, 'manager'),
            department: ArrayReader::reference($data, 'department'),
            location: ArrayReader::reference($data, 'location'),
            class: ArrayReader::reference($data, 'class'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
