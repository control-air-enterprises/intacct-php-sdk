<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\OAuth;

use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\RefreshToken;
use ControlAir\Intacct\Auth\Tokens\TokenIntrospection;
use ControlAir\Intacct\Auth\Tokens\TokenSet;
use ControlAir\Intacct\Auth\Tokens\TokenType;
use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\Configuration\OAuthEndpoints;
use ControlAir\Intacct\Exceptions\AuthenticationException;
use ControlAir\Intacct\Exceptions\TransportException;
use DateTimeImmutable;
use JsonException;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

final readonly class OAuthClient
{
    public function __construct(
        private OAuthApplication $application,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private ClockInterface $clock,
        private OAuthEndpoints $endpoints = new OAuthEndpoints,
    ) {}

    public function authorizationUrl(AuthorizationRequest $authorization): string
    {
        $query = [
            'response_type' => 'code',
            'client_id' => $this->application->clientId,
            'redirect_uri' => $authorization->redirectUri,
            'state' => $authorization->state,
        ];

        if ($authorization->scopes !== []) {
            $query['scope'] = implode(' ', $authorization->scopes);
        }

        if ($authorization->pkce !== null) {
            $query['code_challenge'] = $authorization->pkce->challenge;
            $query['code_challenge_method'] = 'S256';
        }

        return $this->endpoints->authorizeUri.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchange(AuthorizationCodeGrant $grant): TokenSet
    {
        $fields = [
            'grant_type' => 'authorization_code',
            'code' => $grant->code,
            'redirect_uri' => $grant->redirectUri,
            'client_id' => $this->application->clientId,
        ];

        if ($grant->pkce !== null) {
            $fields['code_verifier'] = $grant->pkce->verifier();
        } else {
            $fields['client_secret'] = $this->application->requireClientSecret();
        }

        return $this->mapTokenSet($this->postForm($this->endpoints->tokenUri, $fields));
    }

    public function refresh(RefreshTokenGrant $grant): TokenSet
    {
        $fields = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $grant->refreshToken->reveal(),
            'client_id' => $this->application->clientId,
        ];

        if ($this->application->clientSecret() !== null) {
            $fields['client_secret'] = $this->application->clientSecret();
        }

        if ($grant->entityId !== null) {
            $fields['entity_id'] = $grant->entityId;
        }

        return $this->mapTokenSet(
            $this->postForm($this->endpoints->tokenUri, $fields),
            $grant->refreshToken,
        );
    }

    public function clientCredentials(ClientCredentialsGrant $grant): TokenSet
    {
        $fields = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->application->clientId,
            'client_secret' => $this->application->requireClientSecret(),
        ];

        if ($grant->username !== null) {
            $fields['username'] = $grant->username;
        } else {
            $fields['session_id'] = $grant->sessionId;
        }

        return $this->mapTokenSet($this->postJson($this->endpoints->tokenUri, $fields));
    }

    public function revoke(AccessToken|RefreshToken $token): bool
    {
        $payload = $this->postForm($this->endpoints->revokeUri, [
            'client_id' => $this->application->clientId,
            'client_secret' => $this->application->requireClientSecret(),
            'token' => $token->reveal(),
        ]);

        return ($payload['revoked'] ?? false) === true;
    }

    public function introspect(
        AccessToken $token,
        IntrospectionAuthentication $authentication = IntrospectionAuthentication::Bearer,
    ): TokenIntrospection {
        if ($authentication === IntrospectionAuthentication::ClientCredentials) {
            $credentials = $this->application->clientId.':'.$this->application->requireClientSecret();
            $headers['Authorization'] = 'Basic '.base64_encode($credentials);
        } else {
            $headers['Authorization'] = 'Bearer '.$token->reveal();
        }

        $payload = $this->postForm(
            $this->endpoints->introspectUri,
            ['token' => $token->reveal()],
            $headers,
        );

        if (($payload['active'] ?? false) !== true) {
            return new TokenIntrospection(active: false);
        }

        return new TokenIntrospection(
            active: true,
            tokenType: isset($payload['token_type']) && is_string($payload['token_type'])
                ? TokenType::fromResponse($payload['token_type'])
                : null,
            clientId: $this->nullableString($payload, 'client_id'),
            userId: $this->nullableString($payload, 'user_id'),
            companyId: $this->nullableString($payload, 'cny_id'),
            expiresAt: $this->timestamp($payload, 'exp'),
            issuedAt: $this->timestamp($payload, 'iat'),
            companyKey: $this->nullableString($payload, 'company_key'),
            entityId: $this->nullableString($payload, 'entity_id'),
            entityKey: $this->nullableString($payload, 'entity_key'),
            userKey: $this->nullableString($payload, 'user_key'),
            aiEnabled: isset($payload['ai_enabled']) && is_bool($payload['ai_enabled'])
                ? $payload['ai_enabled']
                : null,
        );
    }

    /**
     * @param  array<string, scalar|null>  $fields
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function postForm(string $uri, array $fields, array $headers = []): array
    {
        return $this->post(
            $uri,
            http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
            'application/x-www-form-urlencoded',
            $headers,
        );
    }

    /**
     * @param  array<string, scalar|null>  $fields
     * @return array<string, mixed>
     */
    private function postJson(string $uri, array $fields): array
    {
        try {
            $body = json_encode($fields, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TransportException('Unable to encode the OAuth request.', previous: $exception);
        }

        return $this->post($uri, $body, 'application/json');
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function post(string $uri, string $body, string $contentType, array $headers = []): array
    {
        $request = $this->requestFactory
            ->createRequest('POST', $uri)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streamFactory->createStream($body));

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new TransportException('The OAuth request could not be sent.', previous: $exception);
        }

        $payload = $this->decode($response);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw $this->authenticationException($response, $payload);
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
     */
    private function authenticationException(ResponseInterface $response, array $payload): AuthenticationException
    {
        $error = $payload;

        if (isset($payload['ia::error']) && is_array($payload['ia::error'])) {
            /** @var array<string, mixed> $error */
            $error = $payload['ia::error'];
        }

        $message = $this->nullableString($error, 'error_description')
            ?? $this->nullableString($error, 'message')
            ?? sprintf('Sage Intacct OAuth request failed with HTTP %d.', $response->getStatusCode());

        return new AuthenticationException(
            message: $message,
            statusCode: $response->getStatusCode(),
            errorCode: $this->nullableString($error, 'code') ?? $this->nullableString($error, 'error'),
            supportId: $this->nullableString($error, 'supportId'),
            response: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapTokenSet(array $payload, ?RefreshToken $fallbackRefreshToken = null): TokenSet
    {
        $accessToken = $this->requiredString($payload, 'access_token');
        $tokenType = $this->requiredString($payload, 'token_type');
        $expiresIn = $payload['expires_in'] ?? null;

        if (! is_int($expiresIn) && ! (is_string($expiresIn) && ctype_digit($expiresIn))) {
            throw new TransportException('The OAuth response does not contain a valid expires_in value.');
        }

        $expiresIn = (int) $expiresIn;

        if ($expiresIn <= 0) {
            throw new TransportException('The OAuth expires_in value must be greater than zero.');
        }

        $refreshToken = $fallbackRefreshToken;
        $refreshValue = $this->nullableString($payload, 'refresh_token');

        if ($refreshValue !== null) {
            $refreshToken = new RefreshToken($refreshValue);
        }

        $scopeValue = $this->nullableString($payload, 'scope');
        $scopes = $scopeValue === null ? [] : array_values(array_filter(explode(' ', $scopeValue)));

        return new TokenSet(
            accessToken: new AccessToken($accessToken),
            tokenType: TokenType::fromResponse($tokenType),
            expiresAt: $this->clock->now()->modify(sprintf('+%d seconds', $expiresIn)),
            refreshToken: $refreshToken,
            scopes: $scopes,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requiredString(array $payload, string $key): string
    {
        return $this->nullableString($payload, $key)
            ?? throw new TransportException(sprintf('The OAuth response does not contain a valid %s value.', $key));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function nullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function timestamp(array $payload, string $key): ?DateTimeImmutable
    {
        $value = $payload[$key] ?? null;

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return null;
        }

        try {
            return (new DateTimeImmutable)->setTimestamp((int) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
