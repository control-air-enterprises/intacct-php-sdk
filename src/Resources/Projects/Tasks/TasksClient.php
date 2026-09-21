<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects\Tasks;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class TasksClient
{
    /** @var non-empty-list<string> */
    private const QUERY_FIELDS = [
        'key', 'id', 'name', 'description', 'project.key', 'project.id', 'project.name',
        'planned.startDate', 'planned.endDate', 'actual.startDate', 'actual.endDate',
        'percentComplete', 'observedPercentComplete', 'isMilestone', 'isUtilized',
        'isBillable', 'wbsCode', 'priority', 'taskStatus', 'href',
    ];

    /** @var ResourceGateway<Task> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/projects/task',
            'projects/task',
            Task::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Task
    {
        return $this->gateway->get($key);
    }

    /** @return Page<Task> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select(self::QUERY_FIELDS));
    }

    public function create(CreateTask $task): MutationResult
    {
        return $this->gateway->create($task->toArray());
    }

    public function update(ObjectKey $key, UpdateTask $task): MutationResult
    {
        return $this->gateway->update($key, $task->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
