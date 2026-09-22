<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Composite;

use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\Core\Composite\CompositeClient;
use ControlAir\Intacct\Core\Composite\CompositeItemResult;
use ControlAir\Intacct\Core\Composite\CompositeOperation;
use ControlAir\Intacct\Core\Composite\CompositeReference;
use ControlAir\Intacct\Core\Composite\CompositeRequest;
use ControlAir\Intacct\Core\Composite\CompositeResult;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\HttpMethod;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Exceptions\ApiException;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompositeClient::class)]
#[CoversClass(CompositeRequest::class)]
#[CoversClass(CompositeOperation::class)]
#[CoversClass(CompositeReference::class)]
#[CoversClass(CompositeResult::class)]
#[CoversClass(CompositeItemResult::class)]
final class CompositeClientTest extends TestCase
{
    // Verbatim examples from the composite spec.
    private const REQUEST_INDEPENDENT = '[{"method":"PATCH","path":"/objects/company-config/employee/26","body":{"location":{"key":"6"}}},{"method":"PATCH","path":"/objects/company-config/employee/33","body":{"location":{"key":"6"}}}]';

    private const RESPONSE_INDEPENDENT = '{"ia::result":[{"ia::result":{"key":"26","id":"0014","href":"/objects/company-config/employee/26"},"ia::meta":{"totalCount":1},"ia::status":200},{"ia::result":{"key":"33","id":"1","href":"/objects/company-config/employee/33"},"ia::meta":{"totalCount":1},"ia::status":200}],"ia::meta":{"totalCount":3}}';

    private const REQUEST_REFERENCES = '[{"method":"GET","path":"/objects/company-config/employee/52","resultReference":"employee"},{"method":"POST","path":"/services/core/query","body":{"object":"employee","fields":["id","jobTitle","department.key","employeeType.id"],"filters":[{"$eq":{"department.key":"@{employee.department.key}"}},{"$eq":{"employeeType.id":"@{employee.employeeType.id}"}}],"filterExpression":"1 and 2","orderBy":[{"id":"asc"}]}}]';

    private const RESPONSE_REFERENCES = '{"ia::result":[{"ia::result":{"key":"52","id":"Emp2","jobTitle":"Sr Software Eng","department":{"id":"10","key":"10","name":"QA - II","href":"/objects/company-config/department/10"},"employeeType":{"id":"Part Time","key":"2","href":"/objects/company-config/employee-type/2"},"href":"/objects/company-config/employee/52"},"ia::meta":{"totalCount":1},"ia::status":200},{"ia::result":[{"id":"Emp2","jobTitle":"Sr Software Eng","department.key":"10","employeeType.id":"Part Time"},{"id":"Emp10","jobTitle":"QA Engineer","department.key":"10","employeeType.id":"Part Time"}],"ia::meta":{"totalCount":3,"start":1,"pageSize":100,"next":null,"previous":null},"ia::status":200}],"ia::meta":{"totalCount":2}}';

    private const REQUEST_MULTI_STATUS = '[{"method":"GET","path":"/services/core/async/job-status?jobId=NjQ2NTc2MzAzMVl1Ul9qVmd6M2t4M2pPdEJya2J5Y2dBQUFBQTE"},{"method":"GET","path":"/objects/company-config/contact/2662"}]';

    private const RESPONSE_MULTI_STATUS = '{"ia::result":[{"ia::error":{"code":"notFound","message":"Asynchronous job 6465763031YSPy9lgWtJCMGZ6UkUbA6QAAAAY1 status could not be found","supportId":"tqKR0%7EYsTZeDEdVao0_h01dZFQqgAAAAY"},"ia::status":404},{"ia::error":{"code":"unprocessed","message":"Operation skipped due to atomic transaction failure"},"ia::status":422}],"ia::meta":{"totalCount":2,"totalSuccess":0,"totalError":2}}';

    // Sample from the bulk-requests guide.
    private const REQUEST_GUIDE = '[{"method":"POST","path":"/objects/accounts-payable/vendor","body":{},"resultReference":"vendor","headers":{"X-ABC":"123"}},{"method":"GET","path":"/objects/accounts-payable/vendor/@{vendor.1.key}"}]';

    private const RESPONSE_GUIDE = '{"ia::result":[{"ia::result":[{}],"ia::meta":{"totalCount":1},"ia::status":200,"ia::headers":{"X-ORM-ACTION":"create"}},{"ia::result":[{}],"ia::meta":{"totalCount":1},"ia::status":200}],"ia::meta":{"totalCount":2,"totalSuccess":2,"totalError":0}}';

    public function test_it_posts_the_spec_independent_requests_example_and_maps_the_200_response(): void
    {
        [$client, $http] = $this->client(new Response(200, [], self::RESPONSE_INDEPENDENT));

        $result = $client->execute(CompositeRequest::of(
            CompositeOperation::patch('/objects/company-config/employee/26', ['location' => ['key' => '6']]),
            CompositeOperation::patch('/objects/company-config/employee/33', ['location' => ['key' => '6']]),
        ));

        $request = $http->requests[0];

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/services/core/composite', (string) $request->getUri());
        self::assertSame(self::REQUEST_INDEPENDENT, $this->body($request->getBody()->__toString()));

        self::assertTrue($result->isSuccessful());
        self::assertNull($result->firstFailure());
        self::assertSame(200, $result->statusCode);
        self::assertCount(2, $result);
        self::assertSame(3, $result->meta->totalCount);

        $first = $result->item(0);
        self::assertSame(0, $first->position);
        self::assertSame(200, $first->status);
        self::assertTrue($first->isSuccessful());
        self::assertFalse($first->wasSkipped());
        self::assertFalse($first->isList());
        self::assertSame(1, $first->meta?->totalCount);

        $reference = $first->reference();
        self::assertNotNull($reference);
        self::assertSame('26', $reference->key?->value);
        self::assertSame('0014', $reference->id?->value);
        self::assertSame('/objects/company-config/employee/26', $first->object()['href'] ?? null);
        self::assertSame('33', $result->item(1)->reference()?->key?->value);
    }

    public function test_it_builds_the_spec_reference_example_and_maps_object_and_list_results(): void
    {
        [$client, $http] = $this->client(new Response(200, [], self::RESPONSE_REFERENCES));

        $result = $client->execute(CompositeRequest::of(
            CompositeOperation::get('/objects/company-config/employee/52', resultReference: 'employee'),
            CompositeOperation::post('/services/core/query', [
                'object' => 'employee',
                'fields' => ['id', 'jobTitle', 'department.key', 'employeeType.id'],
                'filters' => [
                    ['$eq' => ['department.key' => CompositeReference::to('employee', 'department', 'key')]],
                    ['$eq' => ['employeeType.id' => CompositeReference::to('employee', 'employeeType', 'id')]],
                ],
                'filterExpression' => '1 and 2',
                'orderBy' => [['id' => 'asc']],
            ]),
        ));

        self::assertSame(self::REQUEST_REFERENCES, $this->body((string) $http->requests[0]->getBody()));

        $employee = $result->item(0)->object();
        self::assertSame('QA - II', is_array($employee['department'] ?? null) ? $employee['department']['name'] : null);

        $query = $result->item(1);
        self::assertTrue($query->isList());
        self::assertNull($query->object());
        self::assertCount(2, $query->rows());
        self::assertSame('Emp10', $query->rows()[1]['id']);
        self::assertNotNull($query->meta);
        self::assertSame(1, $query->meta->start);
        self::assertSame(100, $query->meta->pageSize);
        self::assertNull($query->meta->next);
        self::assertSame(2, $result->meta->totalCount);
    }

    public function test_it_maps_the_spec_207_multi_status_example_without_throwing(): void
    {
        [$client, $http] = $this->client(new Response(207, [], self::RESPONSE_MULTI_STATUS));

        $result = $client->execute(CompositeRequest::of(
            CompositeOperation::get('/services/core/async/job-status?jobId=NjQ2NTc2MzAzMVl1Ul9qVmd6M2t4M2pPdEJya2J5Y2dBQUFBQTE'),
            CompositeOperation::get('/objects/company-config/contact/2662'),
        ));

        self::assertSame(self::REQUEST_MULTI_STATUS, $this->body((string) $http->requests[0]->getBody()));
        self::assertSame(207, $result->statusCode);
        self::assertFalse($result->isSuccessful());
        self::assertSame(2, $result->meta->totalCount);
        self::assertSame(0, $result->meta->totalSuccess);
        self::assertSame(2, $result->meta->totalError);

        $failed = $result->item(0);
        self::assertSame($failed, $result->firstFailure());
        self::assertSame(404, $failed->status);
        self::assertFalse($failed->isSuccessful());
        self::assertFalse($failed->wasSkipped());
        self::assertNull($failed->result);
        self::assertSame('notFound', $failed->error?->code);
        self::assertSame('tqKR0%7EYsTZeDEdVao0_h01dZFQqgAAAAY', $failed->error->supportId);

        $skipped = $result->item(1);
        self::assertSame(422, $skipped->status);
        self::assertTrue($skipped->wasSkipped());
        self::assertSame('unprocessed', $skipped->error?->code);
        self::assertSame([$skipped], $result->skipped());
    }

    public function test_it_builds_the_guide_sample_with_indexed_references_and_sub_request_headers(): void
    {
        [$client, $http] = $this->client(new Response(200, [], self::RESPONSE_GUIDE));

        $result = $client->execute(
            (new CompositeRequest)
                ->with(CompositeOperation::post('/objects/accounts-payable/vendor', [], 'vendor', ['X-ABC' => '123']))
                ->with(CompositeOperation::get('/objects/accounts-payable/vendor/'.CompositeReference::to('vendor', 1, 'key'))),
        );

        self::assertSame(self::REQUEST_GUIDE, $this->body((string) $http->requests[0]->getBody()));
        self::assertSame(['X-ORM-ACTION' => 'create'], $result->item(0)->headers);
        self::assertTrue($result->item(0)->isList());
        self::assertSame([[]], $result->item(0)->rows());
        self::assertNull($result->item(0)->reference());
        self::assertSame(2, $result->meta->totalSuccess);
    }

    public function test_it_parses_ia_error_as_a_list_and_nested_in_ia_result(): void
    {
        [$client] = $this->client(new Response(207, [], json_encode([
            'ia::result' => [
                ['ia::result' => ['key' => '1'], 'ia::status' => 201],
                [
                    'ia::error' => [
                        ['code' => 'invalidRequest', 'message' => 'Name is required', 'details' => [['code' => 'required', 'target' => 'name']]],
                        ['code' => 'invalidRequest', 'message' => 'Second error'],
                    ],
                    'ia::meta' => ['totalCount' => 1],
                    'ia::status' => 400,
                ],
                ['ia::result' => ['ia::error' => ['code' => 'atomicOperationFailure', 'message' => 'Skipped']], 'ia::status' => 422],
            ],
            'ia::meta' => ['totalCount' => 3, 'totalSuccess' => 1, 'totalError' => 2],
        ], JSON_THROW_ON_ERROR)));

        $result = $client->execute(CompositeRequest::of(
            CompositeOperation::post('/objects/accounts-payable/vendor', ['id' => 'V1']),
            CompositeOperation::post('/objects/accounts-payable/vendor', ['id' => 'V2']),
            CompositeOperation::post('/objects/accounts-payable/vendor', ['id' => 'V3']),
        ));

        self::assertSame('1', $result->item(0)->reference()?->key?->value);

        $listError = $result->item(1)->error;
        self::assertNotNull($listError);
        self::assertSame('Name is required', $listError->message);
        self::assertSame([['code' => 'required', 'target' => 'name']], $listError->details);
        self::assertCount(2, $listError->errors);
        self::assertSame($result->item(1), $result->firstFailure());

        $nested = $result->item(2);
        self::assertSame('atomicOperationFailure', $nested->error?->code);
        self::assertTrue($nested->wasSkipped());
        self::assertNull($nested->result);
    }

    public function test_request_level_failures_still_throw(): void
    {
        [$client] = $this->client(new Response(400, [], '{"ia::result":{"ia::error":{"code":"invalidRequest","message":"A POST request requires a payload","errorId":"REST-1028","additionalInfo":{"messageId":"IA.REQUEST_REQUIRES_A_PAYLOAD","placeholders":{"OPERATION":"POST"},"propertySet":{}},"supportId":"Kxi78%7EZuyXBDEGVHD2UmO1phYXDQAAAAo"}},"ia::meta":{"totalCount":1,"totalSuccess":0,"totalError":1}}'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('A POST request requires a payload');

        $client->execute(CompositeRequest::of(
            CompositeOperation::get('/objects/company-config/contact/1'),
            CompositeOperation::get('/objects/company-config/contact/2'),
        ));
    }

    public function test_it_requires_an_ia_status_on_each_item(): void
    {
        [$client] = $this->client(new Response(200, [], '{"ia::result":[{"ia::result":{"key":"1"}},{"ia::result":{"key":"2"}}]}'));

        $this->expectException(MappingException::class);

        $client->execute(CompositeRequest::of(
            CompositeOperation::get('/objects/company-config/contact/1'),
            CompositeOperation::get('/objects/company-config/contact/2'),
        ));
    }

    public function test_it_builds_reference_strings(): void
    {
        self::assertSame('@{employee.department.key}', CompositeReference::to('employee', 'department', 'key'));
        self::assertSame('@{vendor.1.key}', CompositeReference::to('vendor', 1, 'key'));
        self::assertSame('@{contactRef.id}', CompositeReference::to('contactRef', 'id'));
    }

    /** @return iterable<string, array{string, list<string|int>}> */
    public static function invalidReferences(): iterable
    {
        yield 'no path' => ['vendor', []];
        yield 'dotted name' => ['vendor.1', ['key']];
        yield 'braces in name' => ['@{vendor}', ['key']];
        yield 'dotted segment' => ['vendor', ['department.key']];
        yield 'empty segment' => ['vendor', ['']];
        yield 'negative index' => ['vendor', [-1, 'key']];
    }

    /** @param list<string|int> $path */
    #[DataProvider('invalidReferences')]
    public function test_it_rejects_invalid_references(string $reference, array $path): void
    {
        $this->expectException(InvalidArgument::class);

        CompositeReference::to($reference, ...$path);
    }

    public function test_a_request_needs_at_least_two_operations(): void
    {
        [$client, $http] = $this->client();
        $request = new CompositeRequest(CompositeOperation::get('/objects/company-config/contact/1'));

        try {
            $client->execute($request);
            self::fail('A single-operation composite request was sent.');
        } catch (InvalidArgument $exception) {
            self::assertStringContainsString('between 2 and 10', $exception->getMessage());
        }

        self::assertSame([], $http->requests);

        $this->expectException(InvalidArgument::class);
        CompositeRequest::of(CompositeOperation::get('/objects/company-config/contact/1'));
    }

    public function test_a_request_accepts_at_most_ten_operations(): void
    {
        $ten = CompositeRequest::of(...$this->gets(10));

        self::assertCount(10, $ten);
        self::assertCount(10, $ten->toArray());

        try {
            $ten->with(CompositeOperation::get('/objects/company-config/contact/11'));
            self::fail('An eleventh operation was accepted.');
        } catch (InvalidArgument $exception) {
            self::assertStringContainsString('at most 10', $exception->getMessage());
        }

        $this->expectException(InvalidArgument::class);
        new CompositeRequest(...$this->gets(11));
    }

    public function test_with_returns_a_new_request(): void
    {
        $empty = new CompositeRequest;
        $one = $empty->with(CompositeOperation::get('/objects/company-config/contact/1'));

        self::assertCount(0, $empty);
        self::assertCount(1, $one);
    }

    public function test_result_references_must_be_unique(): void
    {
        $this->expectException(InvalidArgument::class);

        CompositeRequest::of(
            CompositeOperation::get('/objects/company-config/contact/1', 'contact'),
            CompositeOperation::get('/objects/company-config/contact/2', 'contact'),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function validPaths(): iterable
    {
        yield 'object' => ['/objects/accounts-payable/vendor'];
        yield 'service with query string' => ['/services/core/async/job-status?jobId=abc'];
        yield 'workflow' => ['/workflows/purchasing/document/approve'];
        yield 'derived document with space' => ['/objects/purchasing/document::Vendor Invoice'];
        yield 'reference in path' => ['/objects/accounts-payable/vendor/@{vendor.1.key}'];
    }

    #[DataProvider('validPaths')]
    public function test_it_accepts_object_service_and_workflow_paths(string $path): void
    {
        self::assertSame($path, CompositeOperation::get($path)->path);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPaths(): iterable
    {
        yield 'no leading slash' => ['objects/accounts-payable/vendor'];
        yield 'other root' => ['/admin/accounts-payable/vendor'];
        yield 'absolute URL' => ['https://api.intacct.com/ia/api/v1/objects/accounts-payable/vendor'];
        yield 'root only' => ['/objects/'];
        yield 'versioned path' => ['/v1/objects/accounts-payable/vendor'];
        yield 'upper-case application' => ['/objects/Accounts-payable/vendor'];
        yield 'line break' => ["/objects/accounts-payable/vendor\r\nX-Evil: 1"];
    }

    #[DataProvider('invalidPaths')]
    public function test_it_rejects_other_paths(string $path): void
    {
        $this->expectException(InvalidArgument::class);

        CompositeOperation::get($path);
    }

    /** @return iterable<string, array{string}> */
    public static function reservedHeaders(): iterable
    {
        yield 'Authorization' => ['Authorization'];
        yield 'Content-Type' => ['content-type'];
        yield 'Accept' => ['Accept'];
    }

    #[DataProvider('reservedHeaders')]
    public function test_sub_requests_cannot_set_reserved_headers(string $header): void
    {
        $this->expectException(InvalidArgument::class);

        CompositeOperation::get('/objects/company-config/contact/1', headers: [$header => 'x']);
    }

    public function test_with_header_also_rejects_reserved_headers(): void
    {
        $this->expectException(InvalidArgument::class);

        CompositeOperation::get('/objects/company-config/contact/1')->withHeader('Authorization', 'Bearer other');
    }

    public function test_sub_requests_carry_idempotency_keys_for_post_and_patch(): void
    {
        $key = new IdempotencyKey('e9606bb2-5c5e-4d4f-9b1c-2a3b4c5d6e7f');

        self::assertSame(
            ['Idempotency-Key' => $key->value],
            CompositeOperation::post('/objects/company-config/contact', ['id' => 'A'], idempotencyKey: $key)->toArray()['headers'],
        );
        self::assertSame(
            ['X-ABC' => '1', 'Idempotency-Key' => $key->value],
            CompositeOperation::patch('/objects/company-config/contact/1', ['id' => 'A'], headers: ['X-ABC' => '1'])
                ->withIdempotencyKey($key)
                ->toArray()['headers'],
        );

        $this->expectException(InvalidArgument::class);
        CompositeOperation::get('/objects/company-config/contact/1')->withIdempotencyKey($key);
    }

    public function test_only_post_and_patch_take_a_body(): void
    {
        self::assertArrayNotHasKey('body', CompositeOperation::delete('/objects/company-config/contact/1')->toArray());

        $this->expectException(InvalidArgument::class);
        new CompositeOperation(HttpMethod::Get, '/objects/company-config/contact/1', ['id' => 'A']);
    }

    public function test_result_reference_names_are_validated(): void
    {
        self::assertSame('contactRef', CompositeOperation::get('/objects/company-config/contact/1')->withResultReference('contactRef')->resultReference);

        $this->expectException(InvalidArgument::class);
        CompositeOperation::get('/objects/company-config/contact/1', 'contact.ref');
    }

    /** @return list<CompositeOperation> */
    private function gets(int $count): array
    {
        return array_map(
            static fn (int $key): CompositeOperation => CompositeOperation::get('/objects/company-config/contact/'.$key),
            range(1, $count),
        );
    }

    /** Re-encodes a JSON body without escaped slashes so it compares with the spec text. */
    private function body(string $json): string
    {
        return json_encode(json_decode($json, flags: JSON_THROW_ON_ERROR), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @return array{CompositeClient, QueueHttpClient} */
    private function client(Response ...$responses): array
    {
        $http = new QueueHttpClient(...$responses);
        $factory = new HttpFactory;

        return [
            new CompositeClient(new ApiTransport(
                new StaticAccessTokenProvider(new AccessToken('token')),
                $http,
                $factory,
                $factory,
            )),
            $http,
        ];
    }
}
