<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Auth;

use ControlAir\Intacct\Auth\OAuth\OAuthClient;
use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\InMemoryTokenStore;
use ControlAir\Intacct\Auth\Tokens\RefreshToken;
use ControlAir\Intacct\Auth\Tokens\TokenKey;
use ControlAir\Intacct\Auth\Tokens\TokenManager;
use ControlAir\Intacct\Auth\Tokens\TokenSet;
use ControlAir\Intacct\Auth\Tokens\TokenType;
use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\Tests\Support\FrozenClock;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use DateTimeImmutable;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenManager::class)]
final class TokenManagerTest extends TestCase
{
    public function test_it_returns_a_current_token_without_refreshing(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-21T12:00:00+00:00'));
        $http = new QueueHttpClient;
        $store = new InMemoryTokenStore;
        $key = new TokenKey('tenant-1');
        $store->put($key, new TokenSet(
            new AccessToken('current-token'),
            TokenType::Bearer,
            new DateTimeImmutable('2026-09-21T13:00:00+00:00'),
        ));

        $manager = new TokenManager($this->oauth($http, $clock), $store, $clock);

        self::assertSame('current-token', $manager->getValidAccessToken($key)->reveal());
        self::assertCount(0, $http->requests);
    }

    public function test_it_refreshes_an_expiring_token_and_persists_the_rotation(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-21T12:00:00+00:00'));
        $http = new QueueHttpClient(new Response(200, [], json_encode([
            'token_type' => 'Bearer',
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 43_200,
        ], JSON_THROW_ON_ERROR)));
        $store = new InMemoryTokenStore;
        $key = new TokenKey('tenant-1');
        $store->put($key, new TokenSet(
            new AccessToken('expiring-token'),
            TokenType::Bearer,
            new DateTimeImmutable('2026-09-21T12:00:30+00:00'),
            new RefreshToken('old-refresh-token'),
        ));

        $manager = new TokenManager($this->oauth($http, $clock), $store, $clock, refreshLeewaySeconds: 60);

        self::assertSame('new-access-token', $manager->getValidAccessToken($key)->reveal());
        self::assertSame('new-refresh-token', $store->get($key)?->refreshToken?->reveal());
        self::assertCount(1, $http->requests);
    }

    private function oauth(QueueHttpClient $http, FrozenClock $clock): OAuthClient
    {
        $factory = new HttpFactory;

        return new OAuthClient(
            new OAuthApplication('client-id', 'client-secret'),
            $http,
            $factory,
            $factory,
            $clock,
        );
    }
}
