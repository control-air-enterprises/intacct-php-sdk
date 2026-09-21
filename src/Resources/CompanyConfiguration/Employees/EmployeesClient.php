<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Employees;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class EmployeesClient
{
    /** @var non-empty-list<string> */
    private const QUERY_FIELDS = [
        'key', 'id', 'name', 'jobTitle', 'status', 'birthDate', 'startDate', 'endDate',
        'manager.key', 'manager.id', 'location.key', 'location.id', 'department.key',
        'department.id', 'employeeType.key', 'employeeType.id', 'primaryContact.key',
        'primaryContact.id', 'class.key', 'class.id', 'defaultCurrency',
        'isPlaceholderResource', 'href',
    ];

    /** @var ResourceGateway<Employee> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/company-config/employee',
            'company-config/employee',
            Employee::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Employee
    {
        return $this->gateway->get($key);
    }

    /** @return Page<Employee> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select(self::QUERY_FIELDS));
    }

    public function create(CreateEmployee $employee): MutationResult
    {
        return $this->gateway->create($employee->toArray());
    }

    public function update(ObjectKey $key, UpdateEmployee $employee): MutationResult
    {
        return $this->gateway->update($key, $employee->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
