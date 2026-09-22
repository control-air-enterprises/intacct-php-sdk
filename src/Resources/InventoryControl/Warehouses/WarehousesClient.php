<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\Warehouses;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class WarehousesClient
{
    /** @var ResourceGateway<Warehouse> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/inventory-control/warehouse',
            'inventory-control/warehouse',
            Warehouse::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Warehouse
    {
        return $this->gateway->get($key);
    }

    /** @return Page<Warehouse> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'name', 'status', 'location.key', 'location.id',
            'parent.key', 'parent.id', 'manager.key', 'manager.id',
            'isReplenishmentEnabled', 'enableNegativeInv', 'href',
        ]));
    }

    public function create(CreateWarehouse $warehouse, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($warehouse->toArray(), $idempotencyKey);
    }

    public function update(ObjectKey $key, UpdateWarehouse $warehouse, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $warehouse->toArray(), $idempotencyKey);
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreateWarehouse>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreateWarehouse $record): array => $record->toArray(), $records),
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
