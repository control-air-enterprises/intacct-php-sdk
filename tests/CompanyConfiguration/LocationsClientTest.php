<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\CompanyConfiguration;

use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Resources\CompanyConfiguration\Locations\CreateLocation;
use ControlAir\Intacct\Resources\CompanyConfiguration\Locations\Location;
use ControlAir\Intacct\Resources\CompanyConfiguration\Locations\LocationsClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Locations\UpdateLocation;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LocationsClient::class)]
#[CoversClass(Location::class)]
#[CoversClass(CreateLocation::class)]
#[CoversClass(UpdateLocation::class)]
final class LocationsClientTest extends ApiTestCase
{
    /**
     * Every field here was accepted by a live company-config/location query. Sage rejects
     * `description` ("The description field does not exist in company-config/location objects
     * in version 1"), so it must never be selected.
     */
    private const QUERY_FIELDS = [
        'key', 'id', 'name', 'status', 'startDate', 'endDate',
        'reportTitle', 'printAs', 'parent.key', 'parent.id', 'parent.name',
        'manager.key', 'manager.id', 'manager.name', 'entity.key', 'entity.id',
        'entity.name', 'baseCurrency', 'taxId', 'businessId', 'href',
    ];

    public function test_query_selects_only_fields_the_location_object_has(): void
    {
        [$locations, $http] = $this->locations($this->json([
            'ia::result' => [[
                'key' => '4',
                'id' => 'WEST-ANA',
                'name' => 'West - Anaheim',
                'status' => 'active',
                'startDate' => null,
                'endDate' => null,
                'reportTitle' => null,
                'printAs' => 'West Region',
                'parent.key' => '2',
                'parent.id' => 'WEST',
                'parent.name' => 'Western Region',
                'manager.key' => null,
                'manager.id' => null,
                'manager.name' => null,
                'entity.key' => '2',
                'entity.id' => 'WEST',
                'entity.name' => 'Western Region',
                'baseCurrency' => 'USD',
                'taxId' => null,
                'businessId' => null,
                'href' => '/objects/company-config/location/4',
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 5, 'next' => null],
        ]));

        $page = $locations->query(new ResourceQuery(size: 5));

        $location = $page->items[0];
        self::assertSame('4', $location->key->value);
        self::assertSame('WEST-ANA', $location->id->value);
        self::assertSame('West - Anaheim', $location->name);
        self::assertSame(RecordStatus::Active, $location->status);
        self::assertSame('West Region', $location->printAs);
        self::assertSame('WEST', $location->parent?->id?->value);
        self::assertSame('2', $location->parent->key?->value);
        self::assertSame('Western Region', $location->entity?->name);
        self::assertNull($location->manager);
        self::assertSame('USD', $location->baseCurrency);

        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('https://api.intacct.com/ia/api/v1/services/core/query', $this->url($http->requests[0]));
        self::assertSame('company-config/location', $body['object']);
        self::assertSame(self::QUERY_FIELDS, $body['fields']);
        self::assertSame(5, $body['size']);
    }

    public function test_it_maps_a_location_read(): void
    {
        [$locations, $http] = $this->locations($this->json([
            'ia::result' => [
                'key' => '4',
                'id' => 'WEST-ANA',
                'name' => 'West - Anaheim',
                'status' => 'active',
                'startDate' => '2024-01-01',
                'parent' => ['key' => '2', 'id' => 'WEST', 'name' => 'Western Region', 'href' => '/objects/company-config/location/2'],
                'manager' => ['key' => null, 'id' => null, 'name' => null],
                'entity' => ['key' => '2', 'id' => 'WEST', 'name' => 'Western Region', 'href' => '/objects/company-config/entity/2'],
                'locationType' => 'location',
                'baseCurrency' => 'USD',
                'href' => '/objects/company-config/location/4',
            ],
            'ia::meta' => ['totalCount' => 1],
        ]));

        $location = $locations->get(new ObjectKey('4'));

        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/location/4', $this->url($http->requests[0]));
        self::assertSame('2024-01-01', $location->startDate?->value);
        self::assertSame('WEST', $location->parent?->id?->value);
        self::assertSame('WEST', $location->entity?->id?->value);
        self::assertNull($location->manager);
        self::assertSame('/objects/company-config/location/4', $location->href);
    }

    public function test_it_sends_create_and_update_payloads(): void
    {
        [$locations, $http] = $this->locations($this->mutation('15', 'WEST-SD'), $this->mutation('15'));

        $locations->create(new CreateLocation(
            id: new ObjectId('WEST-SD'),
            name: 'West - San Diego',
            status: RecordStatus::Active,
            parent: ObjectReference::byId('WEST'),
            startDate: new LocalDate('2024-01-01'),
            printAs: 'West Region',
        ));
        $locations->update(
            new ObjectKey('15'),
            UpdateLocation::name('West - San Diego County')
                ->withParent(null)
                ->withStatus(RecordStatus::Inactive),
        );

        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/location', $this->url($http->requests[0]));
        self::assertSame([
            'id' => 'WEST-SD',
            'name' => 'West - San Diego',
            'status' => 'active',
            'parent' => ['id' => 'WEST'],
            'startDate' => '2024-01-01',
            'printAs' => 'West Region',
        ], $this->jsonBody($http->requests[0]));
        self::assertSame('PATCH', $http->requests[1]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/location/15', $this->url($http->requests[1]));
        self::assertSame([
            'name' => 'West - San Diego County',
            'parent' => null,
            'status' => 'inactive',
        ], $this->jsonBody($http->requests[1]));
    }

    /** @return array{LocationsClient, QueueHttpClient} */
    private function locations(Response ...$responses): array
    {
        [$transport, $http] = $this->transport(...$responses);

        return [new LocationsClient($transport, new QueryClient($transport)), $http];
    }
}
