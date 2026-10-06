<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ChangeRequests;

use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderExternalReference;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderInternalReference;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderSchedule;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

/**
 * A change request: proposed cost and price changes to a project, by cost code, that a
 * project change order collects for the owner.
 */
final readonly class ChangeRequest
{
    /**
     * @param  ChangeRequestWorkflowType|null  $workflowType  The workflow type of $changeRequestStatus, such as approvedChange.
     * @param  Decimal|null  $totalCost  The sum of the line costs.
     * @param  Decimal|null  $totalPrice  The sum of the line prices.
     * @param  ObjectReference|null  $projectCustomer  The project's customer.
     * @param  list<ChangeRequestLine>  $lines  Empty on query results; read the change request to load its lines.
     */
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?string $description,
        public ?ChangeRequestState $state,
        public ?LocalDate $changeRequestDate,
        public ?LocalDate $costEffectiveDate,
        public ?LocalDate $priceEffectiveDate,
        public ?ObjectReference $changeRequestType,
        public ?ObjectReference $changeRequestStatus,
        public ?ChangeRequestWorkflowType $workflowType,
        public ?Decimal $totalCost,
        public ?Decimal $totalPrice,
        public ?ObjectReference $project,
        public ?ObjectReference $projectCustomer,
        public ?ObjectReference $projectContract,
        public ?ObjectReference $projectContractLine,
        public ?ChangeRequestContractLineSource $projectContractLineSource,
        public ?ObjectReference $projectChangeOrder,
        public ?ObjectReference $location,
        public ?ObjectReference $entity,
        public ?ObjectReference $attachment,
        public ?string $scope,
        public ?string $inclusions,
        public ?string $exclusions,
        public ?string $terms,
        public ChangeOrderSchedule $schedule,
        public ChangeOrderInternalReference $internalReference,
        public ChangeOrderExternalReference $externalReference,
        public array $lines,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $state = ArrayReader::string($data, 'changeRequestState');
        $status = ArrayReader::object($data['changeRequestStatus'] ?? null) ?? [];
        $workflowType = ArrayReader::string($status, 'workflowType');
        $lineSource = ArrayReader::string($data, 'projectContractLineSource');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            description: ArrayReader::string($data, 'description'),
            state: $state === null ? null : ChangeRequestState::tryFrom($state),
            changeRequestDate: ArrayReader::date($data, 'changeRequestDate'),
            costEffectiveDate: ArrayReader::date($data, 'costEffectiveDate'),
            priceEffectiveDate: ArrayReader::date($data, 'priceEffectiveDate'),
            changeRequestType: ArrayReader::reference($data, 'changeRequestType'),
            changeRequestStatus: ArrayReader::reference($data, 'changeRequestStatus'),
            workflowType: $workflowType === null ? null : ChangeRequestWorkflowType::tryFrom($workflowType),
            totalCost: ArrayReader::decimal($data, 'totalCost'),
            totalPrice: ArrayReader::decimal($data, 'totalPrice'),
            project: ArrayReader::reference($data, 'project'),
            projectCustomer: ArrayReader::reference($data, 'projectCustomer'),
            projectContract: ArrayReader::reference($data, 'projectContract'),
            projectContractLine: ArrayReader::reference($data, 'projectContractLine'),
            projectContractLineSource: $lineSource === null ? null : ChangeRequestContractLineSource::tryFrom($lineSource),
            projectChangeOrder: ArrayReader::reference($data, 'projectChangeOrder'),
            location: ArrayReader::reference($data, 'location'),
            entity: ArrayReader::reference($data, 'entity'),
            attachment: ArrayReader::reference($data, 'attachment'),
            scope: ArrayReader::string($data, 'scope'),
            inclusions: ArrayReader::string($data, 'inclusions'),
            exclusions: ArrayReader::string($data, 'exclusions'),
            terms: ArrayReader::string($data, 'terms'),
            schedule: ChangeOrderSchedule::fromArray(ArrayReader::object($data['schedule'] ?? null) ?? []),
            internalReference: ChangeOrderInternalReference::fromArray(
                ArrayReader::object($data['internalReference'] ?? null) ?? [],
            ),
            externalReference: ChangeOrderExternalReference::fromArray(
                ArrayReader::object($data['externalReference'] ?? null) ?? [],
            ),
            lines: array_map(
                ChangeRequestLine::fromArray(...),
                ArrayReader::list($data, 'changeRequestLines'),
            ),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
