<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\Items;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class ItemsClient
{
    /** @var ResourceGateway<Item> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/inventory-control/item',
            'inventory-control/item',
            Item::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Item
    {
        return $this->gateway->get($key);
    }

    /** @return Page<Item> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'name', 'status', 'itemType', 'costMethod', 'productLine.key',
            'productLine.id', 'unitOfMeasureGroup.key', 'unitOfMeasureGroup.id',
            'extendedDescription', 'poDescription', 'soDescription', 'purchasing.standardCost',
            'sales.basePrice', 'sales.isTaxable', 'quantityOnHand', 'quantityOnOrder', 'href',
        ]));
    }

    public function create(CreateItem $item): MutationResult
    {
        return $this->gateway->create($item->toArray());
    }

    public function update(ObjectKey $key, UpdateItem $item): MutationResult
    {
        return $this->gateway->update($key, $item->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
