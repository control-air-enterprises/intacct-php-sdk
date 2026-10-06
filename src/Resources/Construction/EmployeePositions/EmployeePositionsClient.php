<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\EmployeePositions;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class EmployeePositionsClient
{
    /** @var non-empty-list<string> */
    private const QUERY_FIELDS = [
        'key', 'id', 'name', 'description', 'status', 'entity.key', 'entity.id', 'entity.name', 'href',
    ];

    /** @var ResourceGateway<EmployeePosition> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/construction/employee-position',
            'construction/employee-position',
            EmployeePosition::fromArray(...),
        );
    }

    public function get(ObjectKey $key): EmployeePosition
    {
        return $this->gateway->get($key);
    }

    /** @return Page<EmployeePosition> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select(self::QUERY_FIELDS));
    }

    public function create(CreateEmployeePosition $employeePosition, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($employeePosition->toArray(), $idempotencyKey);
    }

    public function update(ObjectKey $key, UpdateEmployeePosition $employeePosition, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $employeePosition->toArray(), $idempotencyKey);
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreateEmployeePosition>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreateEmployeePosition $record): array => $record->toArray(), $records),
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
