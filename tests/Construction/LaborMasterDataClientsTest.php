<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Construction;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Resources\Construction\EmployeePositions\CreateEmployeePosition;
use ControlAir\Intacct\Resources\Construction\EmployeePositions\EmployeePosition;
use ControlAir\Intacct\Resources\Construction\EmployeePositions\EmployeePositionsClient;
use ControlAir\Intacct\Resources\Construction\EmployeePositions\UpdateEmployeePosition;
use ControlAir\Intacct\Resources\Construction\LaborClasses\CreateLaborClass;
use ControlAir\Intacct\Resources\Construction\LaborClasses\LaborClass;
use ControlAir\Intacct\Resources\Construction\LaborClasses\LaborClassesClient;
use ControlAir\Intacct\Resources\Construction\LaborClasses\UpdateLaborClass;
use ControlAir\Intacct\Resources\Construction\LaborShifts\CreateLaborShift;
use ControlAir\Intacct\Resources\Construction\LaborShifts\LaborShift;
use ControlAir\Intacct\Resources\Construction\LaborShifts\LaborShiftsClient;
use ControlAir\Intacct\Resources\Construction\LaborShifts\UpdateLaborShift;
use ControlAir\Intacct\Resources\Construction\LaborUnions\CreateLaborUnion;
use ControlAir\Intacct\Resources\Construction\LaborUnions\LaborUnion;
use ControlAir\Intacct\Resources\Construction\LaborUnions\LaborUnionsClient;
use ControlAir\Intacct\Resources\Construction\LaborUnions\UpdateLaborUnion;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Labor unions, labor classes, labor shifts and employee positions share one shape, so the
 * read side runs once per object through a data provider and each object has its own write test.
 */
#[CoversClass(LaborUnionsClient::class)]
#[CoversClass(LaborUnion::class)]
#[CoversClass(CreateLaborUnion::class)]
#[CoversClass(UpdateLaborUnion::class)]
#[CoversClass(LaborClassesClient::class)]
#[CoversClass(LaborClass::class)]
#[CoversClass(CreateLaborClass::class)]
#[CoversClass(UpdateLaborClass::class)]
#[CoversClass(LaborShiftsClient::class)]
#[CoversClass(LaborShift::class)]
#[CoversClass(CreateLaborShift::class)]
#[CoversClass(UpdateLaborShift::class)]
#[CoversClass(EmployeePositionsClient::class)]
#[CoversClass(EmployeePosition::class)]
#[CoversClass(CreateEmployeePosition::class)]
#[CoversClass(UpdateEmployeePosition::class)]
final class LaborMasterDataClientsTest extends ApiTestCase
{
    private const BASE = 'https://api.intacct.com/ia/api/v1/objects/construction/';

    /**
     * @return iterable<string, array{string, \Closure(ApiTransport, QueryClient): (LaborUnionsClient|LaborClassesClient|LaborShiftsClient|EmployeePositionsClient)}>
     */
    public static function clients(): iterable
    {
        yield 'labor union' => ['labor-union', static fn (ApiTransport $transport, QueryClient $queries): LaborUnionsClient => new LaborUnionsClient($transport, $queries)];
        yield 'labor class' => ['labor-class', static fn (ApiTransport $transport, QueryClient $queries): LaborClassesClient => new LaborClassesClient($transport, $queries)];
        yield 'labor shift' => ['labor-shift', static fn (ApiTransport $transport, QueryClient $queries): LaborShiftsClient => new LaborShiftsClient($transport, $queries)];
        yield 'employee position' => ['employee-position', static fn (ApiTransport $transport, QueryClient $queries): EmployeePositionsClient => new EmployeePositionsClient($transport, $queries)];
    }

    /** @param \Closure(ApiTransport, QueryClient): (LaborUnionsClient|LaborClassesClient|LaborShiftsClient|EmployeePositionsClient) $make */
    #[DataProvider('clients')]
    public function test_it_maps_a_record_with_custom_fields(string $object, \Closure $make): void
    {
        [$client, $http] = $this->make($make, $this->json([
            'ia::result' => [
                'key' => '7',
                'id' => 'L-100',
                'name' => 'Local 100',
                'description' => 'Synthetic fixture',
                'status' => 'active',
                'entity' => ['key' => '2', 'id' => 'WEST', 'name' => 'Western Region', 'href' => '/objects/company-config/entity/2'],
                'audit' => ['createdDateTime' => '2026-01-05T16:20:00Z'],
                'nsp::REGION' => 'South',
                'href' => '/objects/construction/'.$object.'/7',
            ],
            'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
        ]));

        $record = $client->get(new ObjectKey('7'));

        self::assertSame(self::BASE.$object.'/7', $this->url($http->requests[0]));
        self::assertSame('7', $record->key->value);
        self::assertSame('L-100', $record->id->value);
        self::assertSame('Local 100', $record->name);
        self::assertSame('Synthetic fixture', $record->description);
        self::assertSame(RecordStatus::Active, $record->status);
        self::assertSame('Western Region', $record->entity?->name);
        self::assertSame('/objects/construction/'.$object.'/7', $record->href);
        self::assertSame('South', $record->customFields->get('REGION'));
        self::assertFalse($record->customFields->has('audit'));
    }

    /** @param \Closure(ApiTransport, QueryClient): (LaborUnionsClient|LaborClassesClient|LaborShiftsClient|EmployeePositionsClient) $make */
    #[DataProvider('clients')]
    public function test_query_selects_the_default_fields_and_maps_dotted_rows(string $object, \Closure $make): void
    {
        [$client, $http] = $this->make($make, $this->json([
            'ia::result' => [[
                'key' => '7',
                'id' => 'L-100',
                'name' => 'Local 100',
                'description' => null,
                'status' => 'inactive',
                'entity.key' => null,
                'entity.id' => null,
                'entity.name' => null,
                'nsp::REGION' => 'North',
                'href' => '/objects/construction/'.$object.'/7',
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $client->query((new ResourceQuery)->withFields('nsp::REGION'));

        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('construction/'.$object, $body['object']);
        self::assertSame(
            ['key', 'id', 'name', 'description', 'status', 'entity.key', 'entity.id', 'entity.name', 'href', 'nsp::REGION'],
            $body['fields'],
        );
        $record = $page->items[0];
        self::assertSame(RecordStatus::Inactive, $record->status);
        self::assertNull($record->description);
        self::assertNull($record->entity);
        self::assertSame('North', $record->customFields->get('REGION'));
    }

    /** @param \Closure(ApiTransport, QueryClient): (LaborUnionsClient|LaborClassesClient|LaborShiftsClient|EmployeePositionsClient) $make */
    #[DataProvider('clients')]
    public function test_delete_and_delete_many_address_the_object_path(string $object, \Closure $make): void
    {
        [$client, $http] = $this->make($make, new Response(204), new Response(204));

        $deleted = $client->delete(new ObjectKey('7'));
        $batch = $client->deleteMany([new ObjectKey('7'), new ObjectKey('8')], atomic: true);

        self::assertSame('7', $deleted->reference->key?->value);
        self::assertSame('DELETE', $http->requests[0]->getMethod());
        self::assertSame(self::BASE.$object.'/7', $this->url($http->requests[0]));
        self::assertTrue($batch->isSuccessful());
        self::assertSame(self::BASE.$object.'/7,8', $this->url($http->requests[1]));
        self::assertSame('true', $http->requests[1]->getHeaderLine('X-IA-API-Param-Transaction'));
    }

    public function test_labor_union_writes(): void
    {
        [$transport, $http] = $this->transport(...$this->writeResponses());
        $client = new LaborUnionsClient($transport, new QueryClient($transport));

        $client->create(new CreateLaborUnion(new ObjectId('L-100'), 'Local 100', 'Electricians', RecordStatus::Active, $this->customFields()), new IdempotencyKey('union-l100'));
        $client->update(new ObjectKey('7'), UpdateLaborUnion::description(null)->withName('Local 100A')->withStatus(RecordStatus::Inactive)->withCustomField('REGION', null));
        $client->update(new ObjectKey('7'), UpdateLaborUnion::customField('REGION', 'West'));
        $client->createMany([new CreateLaborUnion(new ObjectId('L-100'), 'Local 100'), new CreateLaborUnion(new ObjectId('L-200'), 'Local 200')]);

        $this->assertWrites('labor-union', 'union-l100', $http);
    }

    public function test_labor_class_writes(): void
    {
        [$transport, $http] = $this->transport(...$this->writeResponses());
        $client = new LaborClassesClient($transport, new QueryClient($transport));

        $client->create(new CreateLaborClass(new ObjectId('L-100'), 'Local 100', 'Electricians', RecordStatus::Active, $this->customFields()), new IdempotencyKey('class-l100'));
        $client->update(new ObjectKey('7'), UpdateLaborClass::description(null)->withName('Local 100A')->withStatus(RecordStatus::Inactive)->withCustomField('REGION', null));
        $client->update(new ObjectKey('7'), UpdateLaborClass::customField('REGION', 'West'));
        $client->createMany([new CreateLaborClass(new ObjectId('L-100'), 'Local 100'), new CreateLaborClass(new ObjectId('L-200'), 'Local 200')]);

        $this->assertWrites('labor-class', 'class-l100', $http);
    }

    public function test_labor_shift_writes(): void
    {
        [$transport, $http] = $this->transport(...$this->writeResponses());
        $client = new LaborShiftsClient($transport, new QueryClient($transport));

        $client->create(new CreateLaborShift(new ObjectId('L-100'), 'Local 100', 'Electricians', RecordStatus::Active, $this->customFields()), new IdempotencyKey('shift-l100'));
        $client->update(new ObjectKey('7'), UpdateLaborShift::description(null)->withName('Local 100A')->withStatus(RecordStatus::Inactive)->withCustomField('REGION', null));
        $client->update(new ObjectKey('7'), UpdateLaborShift::customField('REGION', 'West'));
        $client->createMany([new CreateLaborShift(new ObjectId('L-100'), 'Local 100'), new CreateLaborShift(new ObjectId('L-200'), 'Local 200')]);

        $this->assertWrites('labor-shift', 'shift-l100', $http);
    }

    public function test_employee_position_writes(): void
    {
        [$transport, $http] = $this->transport(...$this->writeResponses());
        $client = new EmployeePositionsClient($transport, new QueryClient($transport));

        $client->create(new CreateEmployeePosition(new ObjectId('L-100'), 'Local 100', 'Electricians', RecordStatus::Active, $this->customFields()), new IdempotencyKey('position-l100'));
        $client->update(new ObjectKey('7'), UpdateEmployeePosition::description(null)->withName('Local 100A')->withStatus(RecordStatus::Inactive)->withCustomField('REGION', null));
        $client->update(new ObjectKey('7'), UpdateEmployeePosition::customField('REGION', 'West'));
        $client->createMany([new CreateEmployeePosition(new ObjectId('L-100'), 'Local 100'), new CreateEmployeePosition(new ObjectId('L-200'), 'Local 200')]);

        $this->assertWrites('employee-position', 'position-l100', $http);
    }

    public function test_names_must_not_be_blank(): void
    {
        foreach ([
            static fn (): object => new CreateLaborUnion(new ObjectId('A'), ' '),
            static fn (): object => new CreateLaborClass(new ObjectId('A'), ''),
            static fn (): object => UpdateLaborShift::name(' '),
            static fn (): object => UpdateEmployeePosition::status(RecordStatus::Active)->withName(''),
        ] as $build) {
            try {
                $build();
                self::fail('A blank name was accepted.');
            } catch (InvalidArgument) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @param  \Closure(ApiTransport, QueryClient): (LaborUnionsClient|LaborClassesClient|LaborShiftsClient|EmployeePositionsClient)  $make
     * @return array{LaborUnionsClient|LaborClassesClient|LaborShiftsClient|EmployeePositionsClient, QueueHttpClient}
     */
    private function make(\Closure $make, Response ...$responses): array
    {
        [$transport, $http] = $this->transport(...$responses);

        return [$make($transport, new QueryClient($transport)), $http];
    }

    private function customFields(): CustomFields
    {
        return (new CustomFields)->with('REGION', 'South');
    }

    /** @return list<Response> */
    private function writeResponses(): array
    {
        return [
            $this->mutation('7', 'L-100'),
            $this->mutation('7', 'L-100'),
            $this->mutation('7', 'L-100'),
            $this->json([
                'ia::result' => [
                    ['key' => '7', 'id' => 'L-100', 'ia::status' => 201],
                    ['key' => '8', 'id' => 'L-200', 'ia::status' => 201],
                ],
                'ia::meta' => ['totalCount' => 2, 'totalSuccess' => 2, 'totalError' => 0],
            ]),
        ];
    }

    private function assertWrites(string $object, string $idempotencyKey, QueueHttpClient $http): void
    {
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame(self::BASE.$object, $this->url($http->requests[0]));
        self::assertSame($idempotencyKey, $http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'id' => 'L-100',
            'name' => 'Local 100',
            'description' => 'Electricians',
            'status' => 'active',
            'nsp::REGION' => 'South',
        ], $this->jsonBody($http->requests[0]));

        self::assertSame('PATCH', $http->requests[1]->getMethod());
        self::assertSame(self::BASE.$object.'/7', $this->url($http->requests[1]));
        self::assertSame([
            'description' => null,
            'name' => 'Local 100A',
            'status' => 'inactive',
            'nsp::REGION' => null,
        ], $this->jsonBody($http->requests[1]));
        self::assertSame(['nsp::REGION' => 'West'], $this->jsonBody($http->requests[2]));

        self::assertSame(self::BASE.$object, $this->url($http->requests[3]));
        self::assertSame(
            [['id' => 'L-100', 'name' => 'Local 100'], ['id' => 'L-200', 'name' => 'Local 200']],
            json_decode((string) $http->requests[3]->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
    }
}
