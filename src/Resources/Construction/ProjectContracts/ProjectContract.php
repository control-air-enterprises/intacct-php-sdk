<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContracts;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

/** The owner (prime) contract of a construction project. Its lines are read through the contract lines client. */
final readonly class ProjectContract
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?string $name,
        public ?string $description,
        public ?LocalDate $contractDate,
        public ?RecordStatus $status,
        public ?ObjectReference $project,
        public ?ObjectReference $customer,
        public ?ObjectReference $projectContractType,
        public ?ObjectReference $location,
        public ?ObjectReference $entity,
        public ?bool $billable,
        public ?bool $excludeFromWipReporting,
        public ?string $scope,
        public ?string $inclusions,
        public ?string $exclusions,
        public ?string $terms,
        public ?ProjectContractSummary $summary,
        public ?ProjectContractBilling $billing,
        public ?ProjectContractSchedule $schedule,
        public ?string $internalReferenceNumber,
        public ?string $externalReferenceNumber,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');
        $summary = ArrayReader::object($data['summary'] ?? null);
        $billing = ArrayReader::object($data['billing'] ?? null);
        $schedule = ArrayReader::object($data['schedule'] ?? null);
        $internalReference = ArrayReader::object($data['internalReference'] ?? null) ?? [];
        $externalReference = ArrayReader::object($data['externalReference'] ?? null) ?? [];

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            name: ArrayReader::string($data, 'name'),
            description: ArrayReader::string($data, 'description'),
            contractDate: ArrayReader::date($data, 'contractDate'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            project: ArrayReader::reference($data, 'project'),
            customer: ArrayReader::reference($data, 'customer'),
            projectContractType: ArrayReader::reference($data, 'projectContractType'),
            location: ArrayReader::reference($data, 'location'),
            entity: ArrayReader::reference($data, 'entity'),
            billable: ArrayReader::bool($data, 'isBillable'),
            excludeFromWipReporting: ArrayReader::bool($data, 'excludeFromWIPReporting'),
            scope: ArrayReader::string($data, 'scope'),
            inclusions: ArrayReader::string($data, 'inclusions'),
            exclusions: ArrayReader::string($data, 'exclusions'),
            terms: ArrayReader::string($data, 'terms'),
            summary: $summary === null ? null : ProjectContractSummary::fromArray($summary),
            billing: $billing === null ? null : ProjectContractBilling::fromArray($billing),
            schedule: $schedule === null ? null : ProjectContractSchedule::fromArray($schedule),
            internalReferenceNumber: ArrayReader::string($internalReference, 'referenceNumber'),
            externalReferenceNumber: ArrayReader::string($externalReference, 'referenceNumber'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
