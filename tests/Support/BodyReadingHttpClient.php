<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Consumes the request body from its current position, like a real transport, before delegating.
 */
final class BodyReadingHttpClient implements ClientInterface
{
    /** @var list<string> */
    public array $bodies = [];

    public function __construct(private readonly ClientInterface $client) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->bodies[] = $request->getBody()->getContents();

        return $this->client->sendRequest($request);
    }
}
