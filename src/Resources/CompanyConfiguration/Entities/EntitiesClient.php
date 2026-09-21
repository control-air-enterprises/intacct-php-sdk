<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Entities;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class EntitiesClient
{
    /** @var ResourceGateway<Entity> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/company-config/entity',
            'company-config/entity',
            Entity::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Entity
    {
        return $this->gateway->get($key);
    }

    /** @return Page<Entity> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'name', 'manager.key', 'manager.id', 'manager.name', 'startDate',
            'endDate', 'status', 'federalId', 'firstFiscalMonth', 'baseCurrency.key',
            'baseCurrency.id', 'weekStart', 'isRoot', 'taxId', 'operatingCountry',
            'accountingType', 'isLimitedEntity', 'href',
        ]));
    }
}
