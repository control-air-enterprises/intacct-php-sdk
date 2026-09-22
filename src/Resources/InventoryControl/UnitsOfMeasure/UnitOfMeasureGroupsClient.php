<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class UnitOfMeasureGroupsClient
{
    /** @var ResourceGateway<UnitOfMeasureGroup> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/inventory-control/unit-of-measure-group',
            'inventory-control/unit-of-measure-group',
            UnitOfMeasureGroup::fromArray(...),
        );
    }

    public function get(ObjectKey $key): UnitOfMeasureGroup
    {
        return $this->gateway->get($key);
    }

    /** @return Page<UnitOfMeasureGroup> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'baseUnit', 'abbreviation', 'defaults.inventory.key',
            'defaults.inventory.id', 'defaults.purchaseOrder.key', 'defaults.purchaseOrder.id',
            'defaults.orderEntry.key', 'defaults.orderEntry.id', 'isSystemGenerated', 'href',
        ]));
    }

    public function create(CreateUnitOfMeasureGroup $group): MutationResult
    {
        return $this->gateway->create($group->toArray());
    }

    public function update(ObjectKey $key, UpdateUnitOfMeasureGroup $group): MutationResult
    {
        return $this->gateway->update($key, $group->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
