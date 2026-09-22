<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\Warehouses;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
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

    public function create(CreateWarehouse $warehouse): MutationResult
    {
        return $this->gateway->create($warehouse->toArray());
    }

    public function update(ObjectKey $key, UpdateWarehouse $warehouse): MutationResult
    {
        return $this->gateway->update($key, $warehouse->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
