<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Support;

use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\IntacctClient;
use ControlAir\Intacct\Support\ArrayReader;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

abstract class ApiTestCase extends TestCase
{
    /** @return array{IntacctClient, QueueHttpClient} */
    protected function client(Response ...$responses): array
    {
        $http = new QueueHttpClient(...$responses);
        $factory = new HttpFactory;

        return [
            new IntacctClient(
                new StaticAccessTokenProvider(new AccessToken('token')),
                $http,
                $factory,
                $factory,
            ),
            $http,
        ];
    }

    /**
     * For resource clients that are not wired into IntacctClient.
     *
     * @return array{ApiTransport, QueueHttpClient}
     */
    protected function transport(Response ...$responses): array
    {
        $http = new QueueHttpClient(...$responses);
        $factory = new HttpFactory;

        return [
            new ApiTransport(
                new StaticAccessTokenProvider(new AccessToken('token')),
                $http,
                $factory,
                $factory,
            ),
            $http,
        ];
    }

    /** @param array<string, mixed> $payload */
    protected function json(array $payload): Response
    {
        return new Response(200, [], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    protected function mutation(string $key, string $id = 'ID'): Response
    {
        return $this->json([
            'ia::result' => ['key' => $key, 'id' => $id],
            'ia::meta' => ['totalSuccess' => 1, 'totalError' => 0],
        ]);
    }

    /** @return array<string, mixed> */
    protected function jsonBody(RequestInterface $request): array
    {
        $body = ArrayReader::object(json_decode(
            (string) $request->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        ));

        self::assertNotNull($body);

        return $body;
    }

    protected function url(RequestInterface $request): string
    {
        return (string) $request->getUri();
    }
}
