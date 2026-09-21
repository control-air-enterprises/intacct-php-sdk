<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class ProjectsClient
{
    /** @var non-empty-list<string> */
    private const QUERY_FIELDS = [
        'key',
        'id',
        'name',
        'description',
        'projectCurrency',
        'category',
        'status',
        'startDate',
        'endDate',
        'contractAmount',
        'actualAmount',
        'billingType',
        'href',
    ];

    /** @var ResourceGateway<Project> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/projects/project',
            'projects/project',
            Project::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Project
    {
        return $this->gateway->get($key);
    }

    /** @return Page<Project> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select(self::QUERY_FIELDS));
    }

    public function create(CreateProject $project): MutationResult
    {
        return $this->gateway->create($project->toArray());
    }

    public function update(ObjectKey $key, UpdateProject $project): MutationResult
    {
        return $this->gateway->update($key, $project->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
