<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Integration;

use ControlAir\Intacct\Auth\OAuth\ClientCredentialsGrant;
use ControlAir\Intacct\Auth\OAuth\OAuthClient;
use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\Support\SystemClock;
use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class LiveOAuthTest extends TestCase
{
    public function test_client_credentials_token_can_be_issued_and_introspected(): void
    {
        Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

        $clientId = $this->requiredEnvironmentValue('SAGE_INTACCT_CLIENT_ID');
        $clientSecret = $this->requiredEnvironmentValue('SAGE_INTACCT_CLIENT_SECRET');
        $userId = $this->requiredEnvironmentValue('SAGE_INTACCT_USER_ID');
        $companyId = $this->requiredEnvironmentValue('SAGE_INTACCT_COMPANY_ID');
        $entityId = $this->optionalEnvironmentValue('SAGE_INTACCT_ENTITY_ID');

        $factory = new HttpFactory;
        $oauth = new OAuthClient(
            application: new OAuthApplication($clientId, $clientSecret),
            httpClient: new Client([
                'connect_timeout' => 10,
                'timeout' => 30,
            ]),
            requestFactory: $factory,
            streamFactory: $factory,
            clock: new SystemClock,
        );

        $tokens = $oauth->clientCredentials(ClientCredentialsGrant::forUsername(
            userId: $userId,
            companyId: $companyId,
            entityId: $entityId,
        ));

        self::assertGreaterThan(time(), $tokens->expiresAt->getTimestamp());

        $introspection = $oauth->introspect($tokens->accessToken);

        self::assertTrue($introspection->active);
        self::assertSame($clientId, $introspection->clientId);
        self::assertSame($userId, $introspection->userId);
        self::assertSame($companyId, $introspection->companyId);
    }

    private function requiredEnvironmentValue(string $key): string
    {
        $value = $this->optionalEnvironmentValue($key);

        if ($value === null) {
            self::markTestSkipped(sprintf(
                'Set %s in .env to run the live Sage Intacct integration test.',
                $key,
            ));
        }

        return $value;
    }

    private function optionalEnvironmentValue(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
