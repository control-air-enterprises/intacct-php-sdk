<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Users;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class UsersClient
{
    /** @var ResourceGateway<User> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/company-config/user',
            'company-config/user',
            User::fromArray(...),
        );
    }

    public function get(ObjectKey $key): User
    {
        return $this->gateway->get($key);
    }

    /** @return Page<User> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'userName', 'accountEmail', 'adminPrivileges', 'userType',
            'webServices.isEnabled', 'webServices.isRestricted', 'status', 'contact.key',
            'contact.id', 'entity.key', 'entity.id', 'entity.name', 'locations',
            'departments', 'roles', 'href',
        ]));
    }
}
