<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Employees;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use ControlAir\Intacct\ValueObjects\SensitiveString;

final readonly class Employee
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?string $jobTitle,
        public ?RecordStatus $status,
        public ?LocalDate $birthDate,
        public ?LocalDate $startDate,
        public ?LocalDate $endDate,
        public ?ObjectReference $manager,
        public ?ObjectReference $location,
        public ?ObjectReference $department,
        public ?ObjectReference $employeeType,
        public ?ObjectReference $primaryContact,
        public ?ObjectReference $class,
        public ?string $defaultCurrency,
        public ?SensitiveString $ssn,
        public ?bool $placeholderResource,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');
        $ssn = ArrayReader::string($data, 'SSN');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            name: ArrayReader::requiredString($data, 'name'),
            jobTitle: ArrayReader::string($data, 'jobTitle'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            birthDate: ArrayReader::date($data, 'birthDate'),
            startDate: ArrayReader::date($data, 'startDate'),
            endDate: ArrayReader::date($data, 'endDate'),
            manager: ArrayReader::reference($data, 'manager'),
            location: ArrayReader::reference($data, 'location'),
            department: ArrayReader::reference($data, 'department'),
            employeeType: ArrayReader::reference($data, 'employeeType'),
            primaryContact: ArrayReader::reference($data, 'primaryContact'),
            class: ArrayReader::reference($data, 'class'),
            defaultCurrency: ArrayReader::string($data, 'defaultCurrency'),
            ssn: $ssn === null ? null : new SensitiveString($ssn),
            placeholderResource: ArrayReader::bool($data, 'isPlaceholderResource'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
