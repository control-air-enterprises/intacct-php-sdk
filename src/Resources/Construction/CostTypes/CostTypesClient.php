<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\CostTypes;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class CostTypesClient
{
    /** @var non-empty-list<string> */
    private const QUERY_FIELDS = [
        'key', 'id', 'name', 'description', 'status', 'costUnitDescription',
        'project.key', 'project.id', 'project.name', 'task.key', 'task.id', 'task.name',
        'planned.startDate', 'planned.endDate', 'actual.startDate', 'actual.endDate', 'href',
    ];

    /** @var ResourceGateway<CostType> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/construction/cost-type',
            'construction/cost-type',
            CostType::fromArray(...),
        );
    }

    public function get(ObjectKey $key): CostType
    {
        return $this->gateway->get($key);
    }

    /** @return Page<CostType> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select(self::QUERY_FIELDS));
    }

    public function create(CreateCostType $costType): MutationResult
    {
        return $this->gateway->create($costType->toArray());
    }

    public function update(ObjectKey $key, UpdateCostType $costType): MutationResult
    {
        return $this->gateway->update($key, $costType->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
