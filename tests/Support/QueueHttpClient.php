<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class QueueHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $responses;

    public function __construct(ResponseInterface|ClientExceptionInterface ...$responses)
    {
        $this->responses = array_values($responses);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        $response = array_shift($this->responses)
            ?? throw new \RuntimeException('No queued HTTP response is available.');

        if ($response instanceof ClientExceptionInterface) {
            throw $response;
        }

        return $response;
    }
}
