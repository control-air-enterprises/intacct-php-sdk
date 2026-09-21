<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Http;

use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\Configuration\ApiConfiguration;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\HttpMethod;
use ControlAir\Intacct\Exceptions\ApiException;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiTransport::class)]
final class ApiTransportTest extends TestCase
{
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
