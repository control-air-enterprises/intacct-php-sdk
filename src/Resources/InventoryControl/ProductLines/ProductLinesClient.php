<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\ProductLines;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class ProductLinesClient
{
    /** @var ResourceGateway<ProductLine> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/inventory-control/product-line',
            'inventory-control/product-line',
            ProductLine::fromArray(...),
        );
    }

    public function get(ObjectKey $key): ProductLine
    {
        return $this->gateway->get($key);
    }

    /** @return Page<ProductLine> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'description', 'status', 'parent.key', 'parent.id', 'href',
        ]));
    }

    public function create(CreateProductLine $productLine): MutationResult
    {
        return $this->gateway->create($productLine->toArray());
    }

    public function update(ObjectKey $key, UpdateProductLine $productLine): MutationResult
    {
        return $this->gateway->update($key, $productLine->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
