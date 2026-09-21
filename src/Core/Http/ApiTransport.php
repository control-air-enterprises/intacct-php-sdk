<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Http;

use ControlAir\Intacct\Auth\Contracts\AccessTokenProvider;
use ControlAir\Intacct\Configuration\ApiConfiguration;
use ControlAir\Intacct\Exceptions\ApiException;
use ControlAir\Intacct\Exceptions\TransportException;
use ControlAir\Intacct\Support\ArrayReader;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ApiTransport
{
    public function __construct(
        private AccessTokenProvider $tokens,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private ApiConfiguration $configuration = new ApiConfiguration,
    ) {}

    /**
     * @param  array<string, scalar|list<scalar>>  $query
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    public function request(
        HttpMethod $method,
        string $path,
        array $query = [],
        ?array $json = null,
    ): array {
        $uri = $this->configuration->uri($path);

        if ($query !== []) {
            $uri .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $request = $this->requestFactory
            ->createRequest($method->value, $uri)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer '.$this->tokens->getAccessToken()->reveal())
            ->withHeader('User-Agent', $this->configuration->userAgent);

        if ($this->configuration->entityId !== null) {
            $request = $request->withHeader('X-IA-API-Param-Entity', $this->configuration->entityId);
        }

        if ($json !== null) {
            try {
                $body = json_encode($json, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new TransportException('Unable to encode the Sage Intacct API request.', previous: $exception);
            }

            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($body));
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new TransportException('The Sage Intacct API request could not be sent.', previous: $exception);
        }

        $payload = $this->decode($response);
        $error = $this->findError($payload);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300 || $error !== null) {
            throw $this->apiException($response, $payload, $error);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if ($body === '') {
            return [];
        }

        try {
            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TransportException('Sage Intacct returned an invalid JSON response.', previous: $exception);
        }

        if (! is_array($payload)) {
            throw new TransportException('Sage Intacct returned an unexpected JSON response.');
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function findError(array $payload): ?array
    {
        $result = $payload['ia::result'] ?? null;

        if (is_array($result) && isset($result['ia::error']) && is_array($result['ia::error'])) {
            /** @var array<string, mixed> */
            return $result['ia::error'];
        }

        if (isset($payload['ia::error']) && is_array($payload['ia::error'])) {
            /** @var array<string, mixed> */
            return $payload['ia::error'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $error
     */
    private function apiException(ResponseInterface $response, array $payload, ?array $error): ApiException
    {
        $error ??= $payload;
        $additionalInfo = ArrayReader::object($error['additionalInfo'] ?? null) ?? [];

        return new ApiException(
            message: $this->string($error, 'message')
                ?? sprintf('Sage Intacct API request failed with HTTP %d.', $response->getStatusCode()),
            statusCode: $response->getStatusCode(),
            errorCode: $this->string($error, 'code'),
            errorId: $this->string($error, 'errorId'),
            supportId: $this->string($error, 'supportId'),
            additionalInfo: $additionalInfo,
            response: $payload,
        );
    }

    /** @param array<string, mixed> $values */
    private function string(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
