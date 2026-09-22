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
     * Sends a request and returns the decoded payload.
     *
     * HTTP 207 multi-status responses are returned rather than thrown so callers can map
     * per-item results. Any other non-2xx status, or a top-level ia::error, throws an
     * ApiException. Extra headers may not set Authorization, Content-Type or Accept; trying
     * to, or passing a malformed header, throws InvalidArgument before anything is sent.
     *
     * @param  array<string, scalar|list<scalar>>  $query
     * @param  array<string, mixed>|list<mixed>|null  $json  batch and composite bodies are lists
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function request(
        HttpMethod $method,
        string $path,
        array $query = [],
        ?array $json = null,
        array $headers = [],
    ): array {
        return $this->send($method, $path, $query, $json, $headers)->payload;
    }

    /**
     * Like request(), but also exposes the HTTP status code and response headers.
     *
     * @param  array<string, scalar|list<scalar>>  $query
     * @param  array<string, mixed>|list<mixed>|null  $json
     * @param  array<string, string>  $headers
     */
    public function send(
        HttpMethod $method,
        string $path,
        array $query = [],
        ?array $json = null,
        array $headers = [],
    ): ApiResponse {
        $headers = RequestHeaders::validate($headers);
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

        foreach ($headers as $name => $value) {
            $request = $request->withHeader((string) $name, $value);
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

        $status = $response->getStatusCode();
        $payload = $this->decode($response);
        $successful = $status >= 200 && $status < 300;
        $error = $status === ApiResponse::MULTI_STATUS ? null : $this->findError($payload, ! $successful);

        if (! $successful || $error !== null) {
            throw $this->apiException($response, $payload, $error);
        }

        $responseHeaders = [];

        foreach ($response->getHeaders() as $name => $values) {
            $responseHeaders[(string) $name] = array_values($values);
        }

        return new ApiResponse($status, $payload, $responseHeaders);
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
     * Finds a request-level error. A list-shaped ia::result (batch or composite) carries
     * per-item outcomes, so it is only consulted to describe an already failed request.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function findError(array $payload, bool $includeItems): ?array
    {
        $result = $payload['ia::result'] ?? null;

        if (is_array($result) && ! array_is_list($result)) {
            $error = self::errorObject($result['ia::error'] ?? null);

            if ($error !== null) {
                return $error;
            }
        }

        $error = self::errorObject($payload['ia::error'] ?? null);

        if ($error !== null || ! $includeItems || ! is_array($result) || ! array_is_list($result)) {
            return $error;
        }

        foreach ($result as $item) {
            if (! is_array($item)) {
                continue;
            }

            $nested = is_array($item['ia::result'] ?? null) ? $item['ia::result'] : [];
            $error = self::errorObject($item['ia::error'] ?? $nested['ia::error'] ?? null);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * Sage Intacct returns ia::error either as an object or as a list of error objects.
     *
     * @return array<string, mixed>|null
     */
    private static function errorObject(mixed $value): ?array
    {
        if (is_array($value) && array_is_list($value)) {
            $value = $value[0] ?? null;
        }

        return ArrayReader::object($value);
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
