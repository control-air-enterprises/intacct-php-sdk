<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\CompanyConfiguration;

use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Resources\CompanyConfiguration\Users\User;
use ControlAir\Intacct\Resources\CompanyConfiguration\Users\UsersClient;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UsersClient::class)]
#[CoversClass(User::class)]
final class UsersClientTest extends ApiTestCase
{
    /**
     * Every field here was accepted by a live company-config/user query. The owned lists
     * `locations`, `departments`, and `roles` are rejected by the query service ("The
     * locations field does not exist in company-config/user objects in version 1"), with or
     * without a sub-field, so they are only read through get().
     */
    private const QUERY_FIELDS = [
        'key', 'id', 'userName', 'accountEmail', 'adminPrivileges', 'userType',
        'webServices.isEnabled', 'webServices.isRestricted', 'status', 'contact.key',
        'contact.id', 'entity.key', 'entity.id', 'entity.name', 'href',
    ];

    public function test_query_selects_only_fields_the_query_service_accepts(): void
    {
        [$users, $http] = $this->users($this->json([
            'ia::result' => [[
                'key' => '12',
                'id' => 'jsmith',
                'userName' => 'Jane Smith',
                'accountEmail' => 'jane@example.com',
                'adminPrivileges' => 'off',
                'userType' => 'business',
                'webServices.isEnabled' => true,
                'webServices.isRestricted' => false,
                'status' => 'active',
                'contact.key' => '40',
                'contact.id' => 'Smith, Jane',
                'entity.key' => null,
                'entity.id' => null,
                'entity.name' => null,
                'href' => '/objects/company-config/user/12',
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 5, 'next' => null],
        ]));

        $page = $users->query(new ResourceQuery(size: 5));

        $user = $page->items[0];
        self::assertSame('jsmith', $user->id->value);
        self::assertSame('Jane Smith', $user->userName);
        self::assertTrue($user->webServicesEnabled);
        self::assertFalse($user->webServicesRestricted);
        self::assertSame(RecordStatus::Active, $user->status);
        self::assertSame('40', $user->contact?->key?->value);
        self::assertNull($user->entity);
        self::assertSame([], $user->locations);
        self::assertSame([], $user->departments);
        self::assertSame([], $user->roles);

        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('company-config/user', $body['object']);
        self::assertSame(self::QUERY_FIELDS, $body['fields']);
    }

    public function test_a_read_maps_the_owned_lists(): void
    {
        [$users, $http] = $this->users($this->json([
            'ia::result' => [
                'key' => '12',
                'id' => 'jsmith',
                'userName' => 'Jane Smith',
                'status' => 'active',
                'locations' => [['key' => '4', 'id' => 'WEST-ANA', 'href' => '/objects/company-config/location/4']],
                'departments' => [],
                'roles' => [['key' => '3', 'id' => 'Project Manager', 'href' => '/objects/company-config/role/3']],
                'href' => '/objects/company-config/user/12',
            ],
            'ia::meta' => ['totalCount' => 1],
        ]));

        $user = $users->get(new ObjectKey('12'));

        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/user/12', $this->url($http->requests[0]));
        self::assertCount(1, $user->locations);
        self::assertSame('WEST-ANA', $user->locations[0]->id?->value);
        self::assertSame([], $user->departments);
        self::assertSame('Project Manager', $user->roles[0]->id?->value);
    }

    /** @return array{UsersClient, QueueHttpClient} */
    private function users(Response ...$responses): array
    {
        [$transport, $http] = $this->transport(...$responses);

        return [new UsersClient($transport, new QueryClient($transport)), $http];
    }
}
