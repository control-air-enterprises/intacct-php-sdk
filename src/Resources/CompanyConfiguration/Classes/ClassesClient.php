<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Classes;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class ClassesClient
{
    /** @var ResourceGateway<ClassDimension> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/company-config/class',
            'company-config/class',
            ClassDimension::fromArray(...),
        );
    }

    public function get(ObjectKey $key): ClassDimension
    {
        return $this->gateway->get($key);
    }

    /** @return Page<ClassDimension> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'name', 'description', 'status', 'parent.key', 'parent.id',
            'parent.name', 'entity.key', 'entity.id', 'entity.name', 'href',
        ]));
    }

    public function create(CreateClassDimension $class): MutationResult
    {
        return $this->gateway->create($class->toArray());
    }

    public function update(ObjectKey $key, UpdateClassDimension $class): MutationResult
    {
        return $this->gateway->update($key, $class->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
