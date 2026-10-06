<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Integration;

use ControlAir\Intacct\Core\Query\ResourceQuery;
use PHPUnit\Framework\Attributes\Group;

/**
 * Read-only checks of assumptions the unit fixtures cannot prove: query object names,
 * dotted query rows, and the model service. Nothing is created, changed, or deleted.
 */
#[Group('integration')]
final class LiveResourcesTest extends LiveTestCase
{
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
}
