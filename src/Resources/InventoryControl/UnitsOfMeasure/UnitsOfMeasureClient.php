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

final readonly class UnitsOfMeasureClient
{
    /** @var ResourceGateway<UnitOfMeasure> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/inventory-control/unit-of-measure',
            'inventory-control/unit-of-measure',
            UnitOfMeasure::fromArray(...),
        );
    }

    public function get(ObjectKey $key): UnitOfMeasure
    {
        return $this->gateway->get($key);
    }

    /** @return Page<UnitOfMeasure> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'abbreviation', 'parent.key', 'parent.id', 'conversionFactor',
            'numberOfDecimalPlaces', 'isBase', 'href',
        ]));
    }

    public function create(CreateUnitOfMeasure $unit): MutationResult
    {
        return $this->gateway->create($unit->toArray());
    }

    public function update(ObjectKey $key, UpdateUnitOfMeasure $unit): MutationResult
    {
        return $this->gateway->update($key, $unit->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
