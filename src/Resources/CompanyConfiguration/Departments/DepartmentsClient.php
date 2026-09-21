<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Departments;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class DepartmentsClient
{
    /** @var ResourceGateway<Department> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/company-config/department',
            'company-config/department',
            Department::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Department
    {
        return $this->gateway->get($key);
    }

    /** @return Page<Department> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'name', 'reportTitle', 'status', 'parent.key', 'parent.id',
            'parent.name', 'supervisor.key', 'supervisor.id', 'supervisor.name', 'href',
        ]));
    }

    public function create(CreateDepartment $department): MutationResult
    {
        return $this->gateway->create($department->toArray());
    }

    public function update(ObjectKey $key, UpdateDepartment $department): MutationResult
    {
        return $this->gateway->update($key, $department->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
