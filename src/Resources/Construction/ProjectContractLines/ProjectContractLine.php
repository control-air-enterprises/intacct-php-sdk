<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContractLines;

use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\ProjectContractSchedule;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\ProjectContractSummary;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class ProjectContractLine
{
    /**
     * @param  ProjectContractSummary|null  $summary  Price totals Sage derives from the entries and change orders.
     * @param  list<ProjectContractLineEntry>  $entries  Empty on query results; read the line to load its entries.
     */
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?string $name,
        public ?string $description,
        public ObjectReference $projectContract,
        public ?ObjectReference $parent,
        public ?LocalDate $contractLineDate,
        public ?RecordStatus $status,
        public ?ObjectReference $glAccount,
        public ?Decimal $retainagePercentage,
        public ?bool $billable,
        public ?bool $excludeFromGlBudget,
        public ?ProjectContractLineBillingType $billingType,
        public ?ProjectContractLineMaximumBilling $maximumBilling,
        public ?Decimal $maximumBillingAmount,
        public ?bool $summarizeBill,
        public ?string $scope,
        public ?string $inclusions,
        public ?string $exclusions,
        public ?string $terms,
        public ?ProjectContractSummary $summary,
        public ?ProjectContractLineBilling $billing,
        public ?ProjectContractSchedule $schedule,
        public ?string $internalReferenceNumber,
        public ?string $externalReferenceNumber,
        public Dimensions $dimensions,
        public array $entries,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $projectContract = ArrayReader::reference($data, 'projectContract')
            ?? throw new MappingException('A project contract line response must include its project contract reference.');

        $status = ArrayReader::string($data, 'status');
        $billingSetup = ArrayReader::object($data['billingSetup'] ?? null) ?? [];
        $billingType = ArrayReader::string($billingSetup, 'billingType');
        $maximumBilling = ArrayReader::string($billingSetup, 'maximumBilling');
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
            projectContract: $projectContract,
            parent: ArrayReader::reference($data, 'parent'),
            contractLineDate: ArrayReader::date($data, 'contractLineDate'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            glAccount: ArrayReader::reference($data, 'glAccount'),
            retainagePercentage: ArrayReader::decimal($data, 'retainagePercentage'),
            billable: ArrayReader::bool($data, 'isBillable'),
            excludeFromGlBudget: ArrayReader::bool($data, 'excludeFromGLBudget'),
            billingType: $billingType === null ? null : ProjectContractLineBillingType::tryFrom($billingType),
            maximumBilling: $maximumBilling === null ? null : ProjectContractLineMaximumBilling::tryFrom($maximumBilling),
            maximumBillingAmount: ArrayReader::decimal($billingSetup, 'maximumBillingAmount'),
            summarizeBill: ArrayReader::bool($billingSetup, 'summarizeBill'),
            scope: ArrayReader::string($data, 'scope'),
            inclusions: ArrayReader::string($data, 'inclusions'),
            exclusions: ArrayReader::string($data, 'exclusions'),
            terms: ArrayReader::string($data, 'terms'),
            summary: $summary === null ? null : ProjectContractSummary::fromArray($summary),
            billing: $billing === null ? null : ProjectContractLineBilling::fromArray($billing),
            schedule: $schedule === null ? null : ProjectContractSchedule::fromArray($schedule),
            internalReferenceNumber: ArrayReader::string($internalReference, 'referenceNumber'),
            externalReferenceNumber: ArrayReader::string($externalReference, 'referenceNumber'),
            dimensions: Dimensions::fromArray(ArrayReader::object($data['dimensions'] ?? null) ?? []),
            entries: array_map(
                ProjectContractLineEntry::fromArray(...),
                ArrayReader::list($data, 'projectContractLineEntries'),
            ),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
