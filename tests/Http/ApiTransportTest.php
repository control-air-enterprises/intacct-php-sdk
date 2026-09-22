<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Http;

use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\Configuration\ApiConfiguration;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\HttpMethod;
use ControlAir\Intacct\Exceptions\ApiException;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiTransport::class)]
final class ApiTransportTest extends TestCase
{
    /** Verbatim 207 example from the composite spec. */
    private const SPEC_207 = '{"ia::result":[{"ia::error":{"code":"notFound","message":"Asynchronous job 6465763031YSPy9lgWtJCMGZ6UkUbA6QAAAAY1 status could not be found","supportId":"tqKR0%7EYsTZeDEdVao0_h01dZFQqgAAAAY"},"ia::status":404},{"ia::error":{"code":"unprocessed","message":"Operation skipped due to atomic transaction failure"},"ia::status":422}],"ia::meta":{"totalCount":2,"totalSuccess":0,"totalError":2}}';

    public function test_it_sends_an_authenticated_entity_scoped_json_request(): void
    {
        [$transport, $http] = $this->transport(
            new Response(200, [], '{"ia::result":{"key":"42"}}'),
            new ApiConfiguration(
                baseUri: 'https://example.test/ia/api/v1/',
                entityId: 'Central',
                userAgent: 'sdk-test',
            ),
        );

        $result = $transport->request(
            HttpMethod::Post,
            '/objects/projects/project',
            ['limit' => 25],
            ['id' => 'PROJ-001'],
        );

        $request = $http->requests[0];

        self::assertSame(['key' => '42'], $result['ia::result']);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.test/ia/api/v1/objects/projects/project?limit=25', (string) $request->getUri());
        self::assertSame('Bearer secret-access-token', $request->getHeaderLine('Authorization'));
        self::assertSame('Central', $request->getHeaderLine('X-IA-API-Param-Entity'));
        self::assertSame('sdk-test', $request->getHeaderLine('User-Agent'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(['id' => 'PROJ-001'], json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_it_maps_an_intacct_error_even_when_http_status_is_successful(): void
    {
        [$transport] = $this->transport(new Response(200, [], json_encode([
            'ia::result' => [
                'ia::error' => [
                    'code' => 'invalidRequest',
                    'message' => 'The project does not exist.',
                    'supportId' => 'support-123',
                    'additionalInfo' => ['field' => 'project'],
                ],
            ],
        ], JSON_THROW_ON_ERROR)));

        try {
            $transport->request(HttpMethod::Get, 'objects/projects/project/404');
            self::fail('An ApiException was not thrown.');
        } catch (ApiException $exception) {
            self::assertSame(200, $exception->statusCode);
            self::assertSame('invalidRequest', $exception->errorCode);
            self::assertSame('support-123', $exception->supportId);
            self::assertSame(['field' => 'project'], $exception->additionalInfo);
            self::assertSame('The project does not exist.', $exception->getMessage());
        }
    }

    public function test_it_sends_extra_headers_and_lets_them_override_sdk_defaults(): void
    {
        [$transport, $http] = $this->transport(
            new Response(200, [], '{"ia::result":{"key":"1"}}'),
            new ApiConfiguration(entityId: 'Central'),
        );

        $transport->request(HttpMethod::Post, 'objects/company-config/contact', json: ['id' => 'A'], headers: [
            'Idempotency-Key' => 'abc-123',
            'X-IA-API-Param-Entity' => 'West',
        ]);

        $request = $http->requests[0];

        self::assertSame('abc-123', $request->getHeaderLine('Idempotency-Key'));
        self::assertSame('West', $request->getHeaderLine('X-IA-API-Param-Entity'));
        self::assertSame('Bearer secret-access-token', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
    }

    /** @return iterable<string, array{string}> */
    public static function reservedHeaders(): iterable
    {
        yield 'Authorization' => ['Authorization'];
        yield 'lower-case content-type' => ['content-type'];
        yield 'upper-case ACCEPT' => ['ACCEPT'];
    }

    #[DataProvider('reservedHeaders')]
    public function test_it_rejects_reserved_headers_before_sending(string $header): void
    {
        [$transport, $http] = $this->transport(new Response(200, [], '{}'));

        try {
            $transport->request(HttpMethod::Get, 'objects/company-config/contact', headers: [$header => 'x']);
            self::fail('An InvalidArgument exception was not thrown.');
        } catch (InvalidArgument $exception) {
            self::assertStringContainsString($header, $exception->getMessage());
        }

        self::assertSame([], $http->requests);
    }

    public function test_it_rejects_malformed_header_names(): void
    {
        [$transport] = $this->transport(new Response(200, [], '{}'));

        $this->expectException(InvalidArgument::class);

        $transport->request(HttpMethod::Get, 'objects/x', headers: ['Bad Header' => 'x']);
    }

    public function test_it_rejects_header_values_with_line_breaks(): void
    {
        [$transport] = $this->transport(new Response(200, [], '{}'));

        $this->expectException(InvalidArgument::class);

        $transport->request(HttpMethod::Get, 'objects/x', headers: ['X-Injected' => "a\r\nAuthorization: Bearer evil"]);
    }

    public function test_it_encodes_list_bodies_as_json_arrays(): void
    {
        [$transport, $http] = $this->transport(new Response(200, [], '{"ia::result":[],"ia::meta":{"totalCount":0}}'));

        $payload = $transport->request(HttpMethod::Post, 'services/core/composite', json: [
            ['method' => 'GET', 'path' => '/objects/company-config/employee/26'],
            ['method' => 'GET', 'path' => '/objects/company-config/employee/33'],
        ]);

        self::assertSame([], $payload['ia::result']);
        self::assertSame(
            '[{"method":"GET","path":"\/objects\/company-config\/employee\/26"},{"method":"GET","path":"\/objects\/company-config\/employee\/33"}]',
            (string) $http->requests[0]->getBody(),
        );
    }

    public function test_it_returns_a_multi_status_payload_instead_of_throwing(): void
    {
        [$transport] = $this->transport(new Response(207, ['X-IA-Throttle-Limit' => '100'], self::SPEC_207));

        $response = $transport->send(HttpMethod::Post, 'services/core/composite', json: [['method' => 'GET', 'path' => '/objects/a/b']]);

        self::assertTrue($response->isMultiStatus());
        self::assertSame(207, $response->statusCode);
        self::assertSame('100', $response->header('x-ia-throttle-limit'));
        self::assertSame(json_decode(self::SPEC_207, true, flags: JSON_THROW_ON_ERROR), $response->payload);
    }

    public function test_request_also_returns_the_multi_status_payload(): void
    {
        [$transport] = $this->transport(new Response(207, [], self::SPEC_207));

        $payload = $transport->request(HttpMethod::Post, 'services/core/composite', json: []);

        self::assertIsList($payload['ia::result']);
    }

    public function test_per_item_errors_in_a_successful_list_result_are_not_a_request_error(): void
    {
        [$transport] = $this->transport(new Response(200, [], json_encode([
            'ia::result' => [
                ['key' => '1', 'ia::status' => 201],
                ['ia::status' => 400, 'ia::error' => ['code' => 'invalidRequest', 'message' => 'Bad record']],
            ],
        ], JSON_THROW_ON_ERROR)));

        $payload = $transport->request(HttpMethod::Post, 'objects/company-config/contact', json: [['id' => 'A'], ['id' => 'B']]);

        self::assertIsList($payload['ia::result']);
    }

    public function test_it_maps_the_verbatim_spec_400_response(): void
    {
        [$transport] = $this->transport(new Response(400, [], '{"ia::result":{"ia::error":{"code":"invalidRequest","message":"A POST request requires a payload","errorId":"REST-1028","additionalInfo":{"messageId":"IA.REQUEST_REQUIRES_A_PAYLOAD","placeholders":{"OPERATION":"POST"},"propertySet":{}},"supportId":"Kxi78%7EZuyXBDEGVHD2UmO1phYXDQAAAAo"}},"ia::meta":{"totalCount":1,"totalSuccess":0,"totalError":1}}'));

        try {
            $transport->request(HttpMethod::Post, 'services/core/composite');
            self::fail('An ApiException was not thrown.');
        } catch (ApiException $exception) {
            self::assertSame(400, $exception->statusCode);
            self::assertSame('invalidRequest', $exception->errorCode);
            self::assertSame('REST-1028', $exception->errorId);
            self::assertSame('Kxi78%7EZuyXBDEGVHD2UmO1phYXDQAAAAo', $exception->supportId);
            self::assertSame('A POST request requires a payload', $exception->getMessage());
            self::assertSame('IA.REQUEST_REQUIRES_A_PAYLOAD', $exception->additionalInfo['messageId']);
        }
    }

    public function test_a_failed_list_response_is_described_by_its_first_item_error(): void
    {
        [$transport] = $this->transport(new Response(400, [], json_encode([
            'ia::result' => [
                ['ia::status' => 400, 'ia::error' => [['code' => 'duplicateId', 'message' => 'CYoung already exists']]],
            ],
        ], JSON_THROW_ON_ERROR)));

        try {
            $transport->request(HttpMethod::Post, 'objects/company-config/contact', json: [['id' => 'CYoung']]);
            self::fail('An ApiException was not thrown.');
        } catch (ApiException $exception) {
            self::assertSame('duplicateId', $exception->errorCode);
            self::assertSame('CYoung already exists', $exception->getMessage());
            self::assertIsList($exception->response['ia::result']);
        }
    }

    public function test_it_maps_a_top_level_error_list(): void
    {
        [$transport] = $this->transport(new Response(401, [], '{"ia::error":[{"code":"unauthorized","message":"Token expired"}]}'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Token expired');

        $transport->request(HttpMethod::Get, 'objects/x');
    }

    /** @return array{ApiTransport, QueueHttpClient} */
    private function transport(Response $response, ApiConfiguration $configuration = new ApiConfiguration): array
    {
        $http = new QueueHttpClient($response);
        $factory = new HttpFactory;

        return [
            new ApiTransport(
                new StaticAccessTokenProvider(new AccessToken('secret-access-token')),
                $http,
                $factory,
                $factory,
                $configuration,
            ),
            $http,
        ];
    }
}
