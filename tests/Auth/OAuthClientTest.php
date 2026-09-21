<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Auth;

use ControlAir\Intacct\Auth\OAuth\AuthorizationCodeGrant;
use ControlAir\Intacct\Auth\OAuth\AuthorizationRequest;
use ControlAir\Intacct\Auth\OAuth\ClientCredentialsGrant;
use ControlAir\Intacct\Auth\OAuth\IntrospectionAuthentication;
use ControlAir\Intacct\Auth\OAuth\OAuthClient;
use ControlAir\Intacct\Auth\OAuth\PkcePair;
use ControlAir\Intacct\Auth\OAuth\RefreshTokenGrant;
use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\RefreshToken;
use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\Exceptions\AuthenticationException;
use ControlAir\Intacct\Tests\Support\FrozenClock;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use DateTimeImmutable;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OAuthClient::class)]
final class OAuthClientTest extends TestCase
{
    private const NOW = '2026-09-21T12:00:00+00:00';

    public function test_it_builds_an_authorization_url_with_state_scope_and_pkce(): void
    {
        [$oauth] = $this->oauth();
        $pkce = new PkcePair(str_repeat('a', 43));

        $url = $oauth->authorizationUrl(new AuthorizationRequest(
            redirectUri: 'https://example.test/oauth/callback',
            state: 'random-state',
            scopes: ['offline_access'],
            pkce: $pkce,
        ));

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertSame('https', parse_url($url, PHP_URL_SCHEME));
        self::assertSame('api.intacct.com', parse_url($url, PHP_URL_HOST));
        self::assertSame('code', $query['response_type']);
        self::assertSame('client-id', $query['client_id']);
        self::assertSame('https://example.test/oauth/callback', $query['redirect_uri']);
        self::assertSame('random-state', $query['state']);
        self::assertSame('offline_access', $query['scope']);
        self::assertSame($pkce->challenge, $query['code_challenge']);
        self::assertSame('S256', $query['code_challenge_method']);
    }

    public function test_it_exchanges_an_authorization_code_using_form_encoding(): void
    {
        [$oauth, $http] = $this->oauth($this->tokenResponse());

        $tokens = $oauth->exchange(new AuthorizationCodeGrant(
            code: 'authorization-code',
            redirectUri: 'https://example.test/oauth/callback',
        ));

        $request = $http->requests[0];
        parse_str((string) $request->getBody(), $body);

        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame('authorization_code', $body['grant_type']);
        self::assertSame('authorization-code', $body['code']);
        self::assertSame('client-id', $body['client_id']);
        self::assertSame('client-secret', $body['client_secret']);
        self::assertSame('access-token', $tokens->accessToken->reveal());
        self::assertSame('refresh-token', $tokens->refreshToken?->reveal());
        self::assertSame('2026-09-22T00:00:00+00:00', $tokens->expiresAt->format(DATE_ATOM));
    }

    public function test_pkce_exchange_uses_the_verifier_without_a_client_secret(): void
    {
        $http = new QueueHttpClient($this->tokenResponse());
        $factory = new HttpFactory;
        $oauth = new OAuthClient(
            new OAuthApplication('public-client'),
            $http,
            $factory,
            $factory,
            new FrozenClock(new DateTimeImmutable(self::NOW)),
        );
        $pkce = new PkcePair(str_repeat('v', 43));

        $oauth->exchange(new AuthorizationCodeGrant(
            code: 'authorization-code',
            redirectUri: 'https://example.test/oauth/callback',
            pkce: $pkce,
        ));

        parse_str((string) $http->requests[0]->getBody(), $body);

        self::assertSame($pkce->verifier(), $body['code_verifier']);
        self::assertArrayNotHasKey('client_secret', $body);
    }

    public function test_it_uses_json_for_the_client_credentials_grant(): void
    {
        [$oauth, $http] = $this->oauth($this->tokenResponse());

        $oauth->clientCredentials(ClientCredentialsGrant::forUsername(
            userId: 'api-user',
            companyId: 'company',
            entityId: 'Central',
        ));

        $request = $http->requests[0];
        $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($body);
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('client_credentials', $body['grant_type']);
        self::assertSame('api-user@company|Central', $body['username']);
        self::assertArrayNotHasKey('session_id', $body);
    }

    public function test_refresh_preserves_the_existing_refresh_token_when_sage_does_not_rotate_it(): void
    {
        [$oauth, $http] = $this->oauth($this->tokenResponse(includeRefreshToken: false));
        $refreshToken = new RefreshToken('original-refresh-token');

        $tokens = $oauth->refresh(new RefreshTokenGrant($refreshToken, entityId: 'Central'));

        parse_str((string) $http->requests[0]->getBody(), $body);

        self::assertSame('refresh_token', $body['grant_type']);
        self::assertSame('original-refresh-token', $body['refresh_token']);
        self::assertSame('Central', $body['entity_id']);
        self::assertSame('original-refresh-token', $tokens->refreshToken?->reveal());
    }

    public function test_it_maps_sage_error_metadata_to_an_exception(): void
    {
        [$oauth] = $this->oauth(new Response(401, ['Content-Type' => 'application/json'], json_encode([
            'code' => 'invalidRequest',
            'message' => 'Credentials are invalid.',
            'supportId' => 'support-123',
        ], JSON_THROW_ON_ERROR)));

        try {
            $oauth->clientCredentials(ClientCredentialsGrant::forSession('session-id'));
            self::fail('An AuthenticationException was not thrown.');
        } catch (AuthenticationException $exception) {
            self::assertSame(401, $exception->statusCode);
            self::assertSame('invalidRequest', $exception->errorCode);
            self::assertSame('support-123', $exception->supportId);
            self::assertSame('Credentials are invalid.', $exception->getMessage());
        }
    }

    public function test_it_revokes_and_introspects_tokens(): void
    {
        [$oauth, $http] = $this->oauth(
            new Response(200, [], '{"revoked":true}'),
            new Response(200, [], json_encode([
                'active' => true,
                'token_type' => 'bearer',
                'client_id' => 'client-id',
                'user_id' => 'api-user',
                'cny_id' => 'company',
                'exp' => 1_800_000_000,
                'iat' => 1_799_996_400,
                'ai_enabled' => false,
            ], JSON_THROW_ON_ERROR)),
        );
        $token = new AccessToken('access-token');

        self::assertTrue($oauth->revoke($token));
        $introspection = $oauth->introspect($token, IntrospectionAuthentication::ClientCredentials);

        self::assertTrue($introspection->active);
        self::assertSame('api-user', $introspection->userId);
        self::assertSame('company', $introspection->companyId);
        self::assertFalse($introspection->aiEnabled);
        self::assertStringStartsWith('Basic ', $http->requests[1]->getHeaderLine('Authorization'));
    }

    public function test_introspection_uses_the_access_token_as_bearer_authentication_by_default(): void
    {
        [$oauth, $http] = $this->oauth(new Response(200, [], '{"active":false}'));
        $token = new AccessToken('access-token');

        $introspection = $oauth->introspect($token);

        self::assertFalse($introspection->active);
        self::assertSame('Bearer access-token', $http->requests[0]->getHeaderLine('Authorization'));
        self::assertSame('token=access-token', (string) $http->requests[0]->getBody());
    }

    /** @return array{OAuthClient, QueueHttpClient} */
    private function oauth(Response ...$responses): array
    {
        $http = new QueueHttpClient(...$responses);
        $factory = new HttpFactory;

        return [
            new OAuthClient(
                new OAuthApplication('client-id', 'client-secret'),
                $http,
                $factory,
                $factory,
                new FrozenClock(new DateTimeImmutable(self::NOW)),
            ),
            $http,
        ];
    }

    private function tokenResponse(bool $includeRefreshToken = true): Response
    {
        $payload = [
            'token_type' => 'Bearer',
            'access_token' => 'access-token',
            'expires_in' => 43_200,
        ];

        if ($includeRefreshToken) {
            $payload['refresh_token'] = 'refresh-token';
        }

        return new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
