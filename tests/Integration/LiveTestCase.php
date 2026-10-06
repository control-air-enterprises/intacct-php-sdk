<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Integration;

use ControlAir\Intacct\Auth\OAuth\ClientCredentialsGrant;
use ControlAir\Intacct\Auth\OAuth\OAuthClient;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\IntacctClient;
use ControlAir\Intacct\Support\SystemClock;
use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

/**
 * Shared setup for tests against a real Sage Intacct company: credentials come from .env,
 * and a test is skipped, not failed, when a required value is missing.
 */
abstract class LiveTestCase extends TestCase
{
    private static ?IntacctClient $client = null;

    protected function client(): IntacctClient
    {
        if (self::$client !== null) {
            return self::$client;
        }

        Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

        $factory = new HttpFactory;
        $http = new Client(['connect_timeout' => 10, 'timeout' => 30]);
        $oauth = new OAuthClient(
            application: new OAuthApplication(
                $this->requiredEnvironmentValue('SAGE_INTACCT_CLIENT_ID'),
                $this->requiredEnvironmentValue('SAGE_INTACCT_CLIENT_SECRET'),
            ),
            httpClient: $http,
            requestFactory: $factory,
            streamFactory: $factory,
            clock: new SystemClock,
        );
        $tokens = $oauth->clientCredentials(ClientCredentialsGrant::forUsername(
            userId: $this->requiredEnvironmentValue('SAGE_INTACCT_USER_ID'),
            companyId: $this->requiredEnvironmentValue('SAGE_INTACCT_COMPANY_ID'),
            entityId: $this->optionalEnvironmentValue('SAGE_INTACCT_ENTITY_ID'),
        ));

        return self::$client = new IntacctClient(
            tokens: new StaticAccessTokenProvider($tokens->accessToken),
            httpClient: $http,
            requestFactory: $factory,
            streamFactory: $factory,
        );
    }

    protected function requiredEnvironmentValue(string $key): string
    {
        $value = $this->optionalEnvironmentValue($key);

        if ($value === null) {
            self::markTestSkipped(sprintf('Set %s in .env to run the live Sage Intacct integration tests.', $key));
        }

        return $value;
    }

    protected function optionalEnvironmentValue(string $key): ?string
    {
        Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** Prints a progress line, so a live run shows what it found and not just pass/fail. */
    protected function report(string $line = ''): void
    {
        fwrite(STDERR, $line.PHP_EOL);
    }
}
