<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Locations;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class LocationsClient
{
    /** @var ResourceGateway<Location> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/company-config/location',
            'company-config/location',
            Location::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Location
    {
        return $this->gateway->get($key);
    }

    /** @return Page<Location> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'name', 'description', 'status', 'startDate', 'endDate',
            'reportTitle', 'printAs', 'parent.key', 'parent.id', 'parent.name',
            'manager.key', 'manager.id', 'manager.name', 'entity.key', 'entity.id',
            'entity.name', 'baseCurrency', 'taxId', 'businessId', 'href',
        ]));
    }

    public function create(CreateLocation $location): MutationResult
    {
        return $this->gateway->create($location->toArray());
    }

    public function update(ObjectKey $key, UpdateLocation $location): MutationResult
    {
        return $this->gateway->update($key, $location->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
