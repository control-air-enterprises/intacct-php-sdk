<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Integration;

use ControlAir\Intacct\Auth\OAuth\ClientCredentialsGrant;
use ControlAir\Intacct\Auth\OAuth\OAuthClient;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\IntacctClient;
use ControlAir\Intacct\Support\SystemClock;
use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Read-only checks of assumptions the unit fixtures cannot prove: query object names,
 * dotted query rows, and the model service. Nothing is created, changed, or deleted.
 */
#[Group('integration')]
final class LiveResourcesTest extends TestCase
{
    private static ?IntacctClient $client = null;

    public function test_vendor_and_item_queries_map_related_fields(): void
    {
        $client = $this->client();

        $vendors = $client->accountsPayable->vendors->query(new ResourceQuery(size: 5));
        $items = $client->inventory->items->query(new ResourceQuery(size: 5));

        self::assertGreaterThanOrEqual(count($vendors->items), $vendors->meta->totalCount);
        self::assertGreaterThanOrEqual(count($items->items), $items->meta->totalCount);
    }

    public function test_purchasing_documents_can_be_queried_by_transaction_definition(): void
    {
        $client = $this->client();
        $definitions = $client->purchasing->transactionDefinitions->query(new ResourceQuery(size: 20));

        if ($definitions->items === []) {
            self::markTestSkipped('The company has no purchasing transaction definitions.');
        }

        $name = $definitions->items[0]->id->value;
        $documents = $client->purchasing->documents($name)->query(new ResourceQuery(size: 1));

        self::assertGreaterThanOrEqual(count($documents->items), $documents->meta->totalCount);

        if ($documents->items !== []) {
            $document = $client->purchasing->documents($name)->get($documents->items[0]->key);

            self::assertSame($documents->items[0]->key->value, $document->key->value);
        }
    }

    public function test_the_model_service_describes_a_vendor(): void
    {
        $model = $this->client()->model->describe('accounts-payable/vendor');

        self::assertNotNull($model);
        self::assertNotNull($model->field('id'));
    }

    private function client(): IntacctClient
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

    private function requiredEnvironmentValue(string $key): string
    {
        $value = $this->optionalEnvironmentValue($key);

        if ($value === null) {
            self::markTestSkipped(sprintf('Set %s in .env to run the live Sage Intacct integration tests.', $key));
        }

        return $value;
    }

    private function optionalEnvironmentValue(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
