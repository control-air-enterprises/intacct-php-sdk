<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Resource;

use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Resources\InventoryControl\Items\ItemsClient;
use ControlAir\Intacct\Resources\InventoryControl\ProductLines\CreateProductLine;
use ControlAir\Intacct\Resources\InventoryControl\ProductLines\ProductLinesClient;
use ControlAir\Intacct\Resources\InventoryControl\ProductLines\UpdateProductLine;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProductLinesClient::class)]
#[CoversClass(ItemsClient::class)]
final class ClientBatchAndIdempotencyTest extends ApiTestCase
{
    public function test_typed_create_and_update_send_an_idempotency_key(): void
    {
        [$client, $http] = $this->client($this->mutation('3'), $this->mutation('3'));
        $key = new IdempotencyKey('product-line-tools');

        $client->inventory->productLines->create(new CreateProductLine(new ObjectId('TOOLS')), $key);
        $client->inventory->productLines->update(new ObjectKey('3'), UpdateProductLine::description('Tools'), $key);

        self::assertSame('product-line-tools', $http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame('product-line-tools', $http->requests[1]->getHeaderLine('Idempotency-Key'));
    }

    public function test_create_many_posts_typed_records_as_an_atomic_batch(): void
    {
        [$client, $http] = $this->client($this->json([
            'ia::result' => [
                ['key' => '3', 'id' => 'TOOLS', 'ia::status' => 201],
                ['key' => '4', 'id' => 'NAILS', 'ia::status' => 201],
            ],
            'ia::meta' => ['totalCount' => 2, 'totalSuccess' => 2, 'totalError' => 0],
        ]));

        $result = $client->inventory->productLines->createMany([
            new CreateProductLine(new ObjectId('TOOLS')),
            new CreateProductLine(new ObjectId('NAILS'), description: 'Fasteners'),
        ], atomic: true);

        self::assertTrue($result->isSuccessful());
        self::assertSame('4', $result->items[1]->reference?->key?->value);
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/inventory-control/product-line', $this->url($http->requests[0]));
        self::assertSame('true', $http->requests[0]->getHeaderLine('X-IA-API-Param-Transaction'));
        self::assertSame(
            [['id' => 'TOOLS'], ['id' => 'NAILS', 'description' => 'Fasteners']],
            json_decode((string) $http->requests[0]->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function test_delete_and_delete_many_accept_an_empty_204_response(): void
    {
        [$client, $http] = $this->client(new Response(204), new Response(204));

        $deleted = $client->inventory->items->delete(new ObjectKey('12'));
        $batch = $client->inventory->items->deleteMany([new ObjectKey('12'), new ObjectKey('13')]);

        self::assertSame('12', $deleted->reference->key?->value);
        self::assertTrue($batch->isSuccessful());
        self::assertSame('13', $batch->items[1]->reference?->key?->value);
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/inventory-control/item/12,13', $this->url($http->requests[1]));
    }
}
