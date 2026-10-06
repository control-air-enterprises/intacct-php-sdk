<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectChangeOrders;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

/**
 * Owner change orders, `construction/project-change-order`.
 */
final readonly class ProjectChangeOrdersClient
{
    /**
     * Sage rejects `status` in a query ("cannot be queried"), so query results leave it null;
     * read a change order to load it.
     *
     * @var non-empty-list<string>
     */
    private const QUERY_FIELDS = [
        'key', 'id', 'description', 'state', 'projectChangeOrderDate', 'priceEffectiveDate',
        'totalCost', 'totalPrice', 'project.key', 'project.id', 'project.name', 'projectContract.key',
        'projectContract.id', 'projectContract.name', 'projectContractLine.key', 'projectContractLine.id',
        'projectContractLine.name', 'customer.key', 'customer.id', 'customer.name', 'changeRequestStatus.key',
        'changeRequestStatus.id', 'item.key', 'item.id', 'item.name', 'location.key', 'location.id',
        'location.name', 'entity.key', 'entity.id', 'entity.name', 'sendToContact.key', 'sendToContact.id',
        'attachment.key', 'attachment.id', 'scope', 'inclusions', 'exclusions', 'terms',
        ...ChangeOrderSchedule::QUERY_FIELDS,
        ...ChangeOrderInternalReference::QUERY_FIELDS,
        ...ChangeOrderExternalReference::QUERY_FIELDS,
        'href',
    ];

    /** @var ResourceGateway<ProjectChangeOrder> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/construction/project-change-order',
            'construction/project-change-order',
            ProjectChangeOrder::fromArray(...),
        );
    }

    public function get(ObjectKey $key): ProjectChangeOrder
    {
        return $this->gateway->get($key);
    }

    /**
     * Query results leave `status` null because Sage cannot query it; read a change order to load it.
     *
     * @return Page<ProjectChangeOrder>
     */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select(self::QUERY_FIELDS));
    }

    public function create(CreateProjectChangeOrder $changeOrder, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($changeOrder->toArray(), $idempotencyKey);
    }

    public function update(ObjectKey $key, UpdateProjectChangeOrder $changeOrder, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $changeOrder->toArray(), $idempotencyKey);
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreateProjectChangeOrder>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreateProjectChangeOrder $record): array => $record->toArray(), $records),
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
