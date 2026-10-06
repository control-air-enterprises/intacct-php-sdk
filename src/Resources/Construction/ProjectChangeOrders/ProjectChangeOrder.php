<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectChangeOrders;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

/**
 * An owner change order against a project contract, which groups approved change requests.
 */
final readonly class ProjectChangeOrder
{
    /**
     * @param  Decimal|null  $totalCost  The total cost of the change requests on the change order.
     * @param  Decimal|null  $totalPrice  The total price of the change requests on the change order.
     * @param  ObjectReference|null  $customer  The project's customer.
     * @param  ObjectReference|null  $sendToContact  The contact the change order is sent to.
     */
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?string $description,
        public ?ProjectChangeOrderState $state,
        public ?RecordStatus $status,
        public ?LocalDate $projectChangeOrderDate,
        public ?LocalDate $priceEffectiveDate,
        public ?Decimal $totalCost,
        public ?Decimal $totalPrice,
        public ?ObjectReference $project,
        public ?ObjectReference $projectContract,
        public ?ObjectReference $projectContractLine,
        public ?ObjectReference $customer,
        public ?ObjectReference $changeRequestStatus,
        public ?ObjectReference $item,
        public ?ObjectReference $location,
        public ?ObjectReference $entity,
        public ?ObjectReference $sendToContact,
        public ?ObjectReference $attachment,
        public ?string $scope,
        public ?string $inclusions,
        public ?string $exclusions,
        public ?string $terms,
        public ChangeOrderSchedule $schedule,
        public ChangeOrderInternalReference $internalReference,
        public ChangeOrderExternalReference $externalReference,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $state = ArrayReader::string($data, 'state');
        $status = ArrayReader::string($data, 'status');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            description: ArrayReader::string($data, 'description'),
            state: $state === null ? null : ProjectChangeOrderState::tryFrom($state),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            projectChangeOrderDate: ArrayReader::date($data, 'projectChangeOrderDate'),
            priceEffectiveDate: ArrayReader::date($data, 'priceEffectiveDate'),
            totalCost: ArrayReader::decimal($data, 'totalCost'),
            totalPrice: ArrayReader::decimal($data, 'totalPrice'),
            project: ArrayReader::reference($data, 'project'),
            projectContract: ArrayReader::reference($data, 'projectContract'),
            projectContractLine: ArrayReader::reference($data, 'projectContractLine'),
            customer: ArrayReader::reference($data, 'customer'),
            changeRequestStatus: ArrayReader::reference($data, 'changeRequestStatus'),
            item: ArrayReader::reference($data, 'item'),
            location: ArrayReader::reference($data, 'location'),
            entity: ArrayReader::reference($data, 'entity'),
            sendToContact: ArrayReader::reference($data, 'sendToContact'),
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
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
