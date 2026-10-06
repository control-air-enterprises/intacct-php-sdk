<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ChangeRequests;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderExternalReference;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderInternalReference;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderSchedule;
use ControlAir\Intacct\ValueObjects\ObjectKey;

/**
 * Change requests, `construction/change-request`, with their owned
 * `construction/change-request-line` records. The line object itself is read-only
 * (OPTIONS, GET): lines are created with their change request and added, changed or
 * removed through its PATCH.
 */
final readonly class ChangeRequestsClient
{
    /** @var non-empty-list<string> */
    private const QUERY_FIELDS = [
        'key', 'id', 'description', 'changeRequestState', 'changeRequestDate', 'costEffectiveDate',
        'priceEffectiveDate', 'changeRequestType.key', 'changeRequestType.id', 'changeRequestStatus.key',
        'changeRequestStatus.id', 'changeRequestStatus.workflowType', 'totalCost', 'totalPrice', 'project.key',
        'project.id', 'project.name', 'projectCustomer.key', 'projectCustomer.id', 'projectCustomer.name',
        'projectContract.key', 'projectContract.id', 'projectContract.name', 'projectContractLine.key',
        'projectContractLine.id', 'projectContractLine.name', 'projectContractLineSource',
        'projectChangeOrder.key', 'projectChangeOrder.id', 'location.key', 'location.id', 'location.name',
        'entity.key', 'entity.id', 'entity.name', 'attachment.key', 'attachment.id', 'scope', 'inclusions',
        'exclusions', 'terms',
        ...ChangeOrderSchedule::QUERY_FIELDS,
        ...ChangeOrderInternalReference::QUERY_FIELDS,
        ...ChangeOrderExternalReference::QUERY_FIELDS,
        'href',
    ];

    /** @var ResourceGateway<ChangeRequest> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/construction/change-request',
            'construction/change-request',
            ChangeRequest::fromArray(...),
        );
    }

    /** Reads a change request with its lines. */
    public function get(ObjectKey $key): ChangeRequest
    {
        return $this->gateway->get($key);
    }

    /**
     * Query results contain change request headers only; read a change request to load its lines.
     *
     * @return Page<ChangeRequest>
     */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select(self::QUERY_FIELDS));
    }

    public function create(CreateChangeRequest $changeRequest, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($changeRequest->toArray(), $idempotencyKey);
    }

    /** Changes the header and adds, updates or removes lines in one request. */
    public function update(ObjectKey $key, UpdateChangeRequest $changeRequest, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $changeRequest->toArray(), $idempotencyKey);
    }

    /** Deletes a change request with its lines. */
    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreateChangeRequest>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreateChangeRequest $record): array => $record->toArray(), $records),
            $atomic,
            $idempotencyKey,
        );
    }

    /** @param list<ObjectKey> $keys Up to 500 keys; results are matched to keys by position. */
    public function deleteMany(array $keys, bool $atomic = false): BatchResult
    {
        return $this->gateway->deleteMany($keys, $atomic);
    }
}
