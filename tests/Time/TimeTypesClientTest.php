<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Time;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Resources\Time\TimeTypes\CreateTimeType;
use ControlAir\Intacct\Resources\Time\TimeTypes\TimeType;
use ControlAir\Intacct\Resources\Time\TimeTypes\TimeTypesClient;
use ControlAir\Intacct\Resources\Time\TimeTypes\UpdateTimeType;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TimeTypesClient::class)]
#[CoversClass(TimeType::class)]
#[CoversClass(CreateTimeType::class)]
#[CoversClass(UpdateTimeType::class)]
final class TimeTypesClientTest extends ApiTestCase
{
    public function test_it_maps_a_time_type(): void
    {
        [$transport, $http] = $this->transport($this->json([
            'ia::result' => [
                'key' => '3',
                'id' => 'Overtime',
                'status' => 'active',
                'earningType' => ['key' => '2', 'id' => 'Overtime 1.5x'],
                'glAccount' => ['key' => '51', 'id' => '5100', 'name' => 'Direct Labor'],
                'offsetGLAccount' => ['key' => '21', 'id' => '2100', 'name' => 'Accrued Payroll'],
                'entity' => ['key' => null, 'id' => null, 'name' => null],
                'nsp::UNION_CODE' => 'L-12',
                'href' => '/objects/time/time-type/3',
            ],
            'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
        ]));

        $timeType = $this->timeTypes($transport)->get(new ObjectKey('3'));

        self::assertSame('https://api.intacct.com/ia/api/v1/objects/time/time-type/3', $this->url($http->requests[0]));
        self::assertSame('Overtime', $timeType->id->value);
        self::assertSame(RecordStatus::Active, $timeType->status);
        self::assertSame('Overtime 1.5x', $timeType->earningType?->id?->value);
        self::assertSame('5100', $timeType->glAccount?->id?->value);
        self::assertSame('Accrued Payroll', $timeType->offsetGLAccount?->name);
        self::assertNull($timeType->entity);
        self::assertSame('L-12', $timeType->customFields->get('UNION_CODE'));
        self::assertSame('/objects/time/time-type/3', $timeType->href);
    }

    public function test_query_selects_the_default_fields(): void
    {
        [$transport, $http] = $this->transport($this->json([
            'ia::result' => [[
                'key' => '3',
                'id' => 'Overtime',
                'status' => 'inactive',
                'glAccount.key' => '51',
                'glAccount.id' => '5100',
                'earningType.key' => null,
                'earningType.id' => null,
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $this->timeTypes($transport)->query();

        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('time/time-type', $body['object']);
        self::assertSame([
            'key', 'id', 'status', 'earningType.key', 'earningType.id', 'glAccount.key',
            'glAccount.id', 'offsetGLAccount.key', 'offsetGLAccount.id', 'entity.key', 'entity.id', 'href',
        ], $body['fields']);
        self::assertSame(RecordStatus::Inactive, $page->items[0]->status);
        self::assertSame('5100', $page->items[0]->glAccount?->id?->value);
        self::assertNull($page->items[0]->earningType);
    }

    public function test_it_sends_create_update_and_delete_payloads(): void
    {
        [$transport, $http] = $this->transport($this->mutation('3', 'Overtime'), $this->mutation('3'), new Response(204));
        $timeTypes = $this->timeTypes($transport);

        $timeTypes->create(new CreateTimeType(
            id: new ObjectId('Overtime'),
            status: RecordStatus::Active,
            glAccount: ObjectReference::byId('5100'),
            offsetGLAccount: ObjectReference::byId('2100'),
            customFields: new CustomFields(['UNION_CODE' => 'L-12']),
        ), new IdempotencyKey('time-type-overtime'));
        $timeTypes->update(
            new ObjectKey('3'),
            UpdateTimeType::status(RecordStatus::Inactive)->withEarningType(null)->withCustomField('UNION_CODE', null),
        );
        $timeTypes->delete(new ObjectKey('3'));

        self::assertSame('https://api.intacct.com/ia/api/v1/objects/time/time-type', $this->url($http->requests[0]));
        self::assertSame('time-type-overtime', $http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'id' => 'Overtime',
            'status' => 'active',
            'glAccount' => ['id' => '5100'],
            'offsetGLAccount' => ['id' => '2100'],
            'nsp::UNION_CODE' => 'L-12',
        ], $this->jsonBody($http->requests[0]));
        self::assertSame('PATCH', $http->requests[1]->getMethod());
        self::assertSame(
            ['status' => 'inactive', 'earningType' => null, 'nsp::UNION_CODE' => null],
            $this->jsonBody($http->requests[1]),
        );
        self::assertSame('DELETE', $http->requests[2]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/time/time-type/3', $this->url($http->requests[2]));
    }

    public function test_an_omitted_account_is_not_sent_while_an_explicit_null_clears_it(): void
    {
        self::assertSame(['glAccount' => ['key' => '51']], UpdateTimeType::glAccount(ObjectReference::byKey('51'))->toArray());
        self::assertSame(
            ['glAccount' => ['key' => '51'], 'offsetGLAccount' => null],
            UpdateTimeType::glAccount(ObjectReference::byKey('51'))->withOffsetGLAccount(null)->toArray(),
        );
    }

    public function test_create_many_and_delete_many_use_batch_requests(): void
    {
        [$transport, $http] = $this->transport(
            $this->json([
                'ia::result' => [
                    ['key' => '3', 'id' => 'Overtime', 'ia::status' => 201],
                    ['key' => '4', 'id' => 'Double Time', 'ia::status' => 201],
                ],
                'ia::meta' => ['totalCount' => 2, 'totalSuccess' => 2, 'totalError' => 0],
            ]),
            new Response(204),
        );
        $timeTypes = $this->timeTypes($transport);

        $created = $timeTypes->createMany([new CreateTimeType(new ObjectId('Overtime')), new CreateTimeType(new ObjectId('Double Time'))]);
        $deleted = $timeTypes->deleteMany([new ObjectKey('3'), new ObjectKey('4')], atomic: true);

        self::assertSame('4', $created->items[1]->reference?->key?->value);
        self::assertSame(
            [['id' => 'Overtime'], ['id' => 'Double Time']],
            json_decode((string) $http->requests[0]->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertTrue($deleted->isSuccessful());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/time/time-type/3,4', $this->url($http->requests[1]));
        self::assertSame('true', $http->requests[1]->getHeaderLine('X-IA-API-Param-Transaction'));
    }

    private function timeTypes(ApiTransport $transport): TimeTypesClient
    {
        return new TimeTypesClient($transport, new QueryClient($transport));
    }
}
