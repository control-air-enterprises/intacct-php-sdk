<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Resource;

use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchItemResult;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\ResultError;
use ControlAir\Intacct\Exceptions\ApiException;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceGateway::class)]
#[CoversClass(BatchResult::class)]
#[CoversClass(BatchItemResult::class)]
#[CoversClass(ResultError::class)]
#[CoversClass(MutationResult::class)]
final class BatchOperationsTest extends TestCase
{
    private const COLLECTION = 'https://api.intacct.com/ia/api/v1/objects/company-config/contact';

    public function test_create_many_posts_the_guide_batch_to_the_collection_path(): void
    {
        [$gateway, $http] = $this->gateway($this->json([
            'ia::result' => [
                ['key' => '1', 'id' => 'CYoung', 'href' => '/objects/company-config/contact/1'],
                ['key' => '2', 'id' => 'FYoung', 'href' => '/objects/company-config/contact/2'],
            ],
            'ia::meta' => ['totalCount' => 2, 'totalSuccess' => 2, 'totalError' => 0],
        ]));

        $result = $gateway->createMany([
            ['id' => 'CYoung', 'email1' => 'info@bakinggoods.com', 'printAs' => 'Caroline Young'],
            ['id' => 'FYoung', 'email1' => 'info@campingsupplies.com', 'printAs' => 'Fred Young'],
        ]);

        $request = $http->requests[0];

        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::COLLECTION, (string) $request->getUri());
        self::assertSame(
            '[{"id":"CYoung","email1":"info@bakinggoods.com","printAs":"Caroline Young"},{"id":"FYoung","email1":"info@campingsupplies.com","printAs":"Fred Young"}]',
            (string) $request->getBody(),
        );
        self::assertFalse($request->hasHeader('X-IA-API-Param-Transaction'));
        self::assertFalse($request->hasHeader('Idempotency-Key'));

        self::assertTrue($result->isSuccessful());
        self::assertCount(2, $result);
        self::assertSame(2, $result->meta->totalSuccess);
        self::assertSame(0, $result->items[0]->position);
        self::assertSame(200, $result->items[0]->status);
        self::assertSame('1', $result->items[0]->reference?->key?->value);
        self::assertSame('FYoung', $result->items[1]->reference?->id?->value);
        self::assertSame([], $result->failures());
    }

    public function test_atomic_batches_send_the_transaction_header(): void
    {
        [$gateway, $http] = $this->gateway(
            $this->json(['ia::result' => [['key' => '1', 'ia::status' => 201]]]),
            new Response(204),
        );

        $gateway->createMany([['id' => 'A']], atomic: true);
        $gateway->deleteMany([new ObjectKey('1')], atomic: true);

        self::assertSame('true', $http->requests[0]->getHeaderLine('X-IA-API-Param-Transaction'));
        self::assertSame('true', $http->requests[1]->getHeaderLine('X-IA-API-Param-Transaction'));
    }

    public function test_writes_send_the_idempotency_key_header(): void
    {
        $key = new IdempotencyKey('e9606bb2-5c5e-4d4f-9b1c-2a3b4c5d6e7f');
        [$gateway, $http] = $this->gateway(
            $this->mutation('1'),
            $this->mutation('1'),
            $this->json(['ia::result' => [['key' => '2']]]),
            $this->json(['ia::result' => [['key' => '3']]]),
            $this->mutation('4'),
        );

        $gateway->create(['id' => 'A'], $key);
        $gateway->update(new ObjectKey('1'), ['printAs' => 'A'], $key);
        $gateway->createMany([['id' => 'B']], idempotencyKey: $key);
        $gateway->updateMany([['key' => '3', 'printAs' => 'C']], idempotencyKey: $key);
        $gateway->create(['id' => 'D']);

        foreach (array_slice($http->requests, 0, 4) as $request) {
            self::assertSame($key->value, $request->getHeaderLine('Idempotency-Key'));
        }

        self::assertFalse($http->requests[4]->hasHeader('Idempotency-Key'));
    }

    public function test_update_many_patches_the_collection_with_a_key_in_each_record(): void
    {
        [$gateway, $http] = $this->gateway($this->json([
            'ia::result' => [
                ['key' => '10', 'id' => 'A', 'ia::status' => 200],
                ['key' => '11', 'id' => 'B', 'ia::status' => 200],
            ],
        ]));

        $result = $gateway->updateMany([
            ['key' => '10', 'printAs' => 'A'],
            ['printAs' => 'B', 'key' => new ObjectKey('11')],
        ]);

        $request = $http->requests[0];

        self::assertSame('PATCH', $request->getMethod());
        self::assertSame(self::COLLECTION, (string) $request->getUri());
        self::assertSame('[{"key":"10","printAs":"A"},{"key":"11","printAs":"B"}]', (string) $request->getBody());
        self::assertSame('11', $result->items[1]->reference?->key?->value);
    }

    public function test_update_many_requires_a_key_in_every_record(): void
    {
        [$gateway, $http] = $this->gateway();

        try {
            $gateway->updateMany([['key' => '10', 'printAs' => 'A'], ['printAs' => 'B']]);
            self::fail('A record without a key was accepted.');
        } catch (InvalidArgument $exception) {
            self::assertStringContainsString('position 1', $exception->getMessage());
        }

        self::assertSame([], $http->requests);
    }

    public function test_delete_many_joins_encoded_keys_with_commas_and_treats_204_as_success(): void
    {
        [$gateway, $http] = $this->gateway(new Response(204));

        $result = $gateway->deleteMany([new ObjectKey('194'), new ObjectKey('195'), new ObjectKey('310')]);

        self::assertSame('DELETE', $http->requests[0]->getMethod());
        self::assertSame(self::COLLECTION.'/194,195,310', (string) $http->requests[0]->getUri());
        self::assertSame('', (string) $http->requests[0]->getBody());
        self::assertTrue($result->isSuccessful());
        self::assertSame(204, $result->statusCode);
        self::assertSame(
            ['194', '195', '310'],
            array_map(static fn (BatchItemResult $item): ?string => $item->reference?->key?->value, $result->items),
        );
        self::assertSame([0, 1, 2], array_map(static fn (BatchItemResult $item): int => $item->position, $result->items));
    }

    public function test_delete_many_treats_an_empty_200_body_as_success(): void
    {
        [$gateway] = $this->gateway(new Response(200));

        $result = $gateway->deleteMany([new ObjectKey('1'), new ObjectKey('2')]);

        self::assertTrue($result->isSuccessful());
        self::assertCount(2, $result->successes());
    }

    public function test_delete_many_encodes_each_key(): void
    {
        [$gateway, $http] = $this->gateway(new Response(204));

        $gateway->deleteMany([new ObjectKey('A B'), new ObjectKey('C/D')]);

        self::assertSame(self::COLLECTION.'/A%20B,C%2FD', (string) $http->requests[0]->getUri());
    }

    public function test_delete_many_rejects_keys_that_break_positional_matching(): void
    {
        [$gateway] = $this->gateway();

        try {
            $gateway->deleteMany([new ObjectKey('1,2')]);
            self::fail('A key containing a comma was accepted.');
        } catch (InvalidArgument) {
        }

        $this->expectException(InvalidArgument::class);
        $gateway->deleteMany([new ObjectKey('1'), new ObjectKey('1')]);
    }

    public function test_results_are_matched_to_requests_by_position_when_keys_are_missing(): void
    {
        [$gateway] = $this->gateway(new Response(207, [], json_encode([
            'ia::result' => [
                ['ia::status' => 204],
                ['ia::status' => 404, 'ia::error' => ['code' => 'notFound', 'message' => 'Contact 195 was not found', 'errorId' => 'REST-1064']],
                ['ia::status' => 204],
            ],
            'ia::meta' => ['totalCount' => 3, 'totalSuccess' => 2, 'totalError' => 1],
        ], JSON_THROW_ON_ERROR)));

        $result = $gateway->deleteMany([new ObjectKey('194'), new ObjectKey('195'), new ObjectKey('310')]);

        self::assertFalse($result->isSuccessful());
        self::assertSame(207, $result->statusCode);
        self::assertSame(1, $result->meta->totalError);

        [$first, $second, $third] = $result->items;

        self::assertTrue($first->isSuccessful());
        self::assertSame('194', $first->reference?->key?->value);
        self::assertFalse($second->isSuccessful());
        self::assertFalse($second->wasSkipped());
        self::assertSame(1, $second->position);
        self::assertSame('195', $second->reference?->key?->value);
        self::assertSame('notFound', $second->error?->code);
        self::assertSame('REST-1064', $second->error->errorId);
        self::assertTrue($third->isSuccessful());
        self::assertSame('310', $third->reference?->key?->value);
        self::assertSame([$second], $result->failures());
    }

    public function test_items_skipped_by_an_atomic_failure_are_reported(): void
    {
        [$gateway] = $this->gateway(new Response(207, [], json_encode([
            'ia::result' => [
                ['ia::status' => 400, 'ia::error' => [['code' => 'invalidRequest', 'message' => 'printAs is required']]],
                ['ia::status' => 422, 'ia::error' => ['code' => 'atomicOperationFailure', 'message' => 'Operation skipped']],
            ],
        ], JSON_THROW_ON_ERROR)));

        $result = $gateway->createMany([['id' => 'A'], ['id' => 'B', 'printAs' => 'B']], atomic: true);

        self::assertSame('printAs is required', $result->items[0]->error?->message);
        self::assertNull($result->items[0]->reference);
        self::assertFalse($result->items[0]->wasSkipped());
        self::assertTrue($result->items[1]->wasSkipped());
    }

    public function test_a_4xx_response_with_per_record_results_is_mapped_instead_of_thrown(): void
    {
        [$gateway] = $this->gateway(new Response(400, [], json_encode([
            'ia::result' => [
                ['ia::error' => ['code' => 'duplicateId', 'message' => 'CYoung already exists']],
                ['ia::error' => ['code' => 'duplicateId', 'message' => 'FYoung already exists']],
            ],
            'ia::meta' => ['totalCount' => 2, 'totalSuccess' => 0, 'totalError' => 2],
        ], JSON_THROW_ON_ERROR)));

        $result = $gateway->createMany([['id' => 'CYoung'], ['id' => 'FYoung']]);

        self::assertSame(400, $result->statusCode);
        self::assertCount(2, $result->failures());
        self::assertSame(400, $result->items[1]->status);
        self::assertSame('FYoung already exists', $result->items[1]->error?->message);
    }

    public function test_request_level_errors_still_throw(): void
    {
        [$gateway] = $this->gateway(new Response(400, [], '{"ia::result":{"ia::error":{"code":"invalidRequest","message":"A POST request requires a payload","errorId":"REST-1028"}},"ia::meta":{"totalCount":1,"totalSuccess":0,"totalError":1}}'));

        $this->expectException(ApiException::class);

        $gateway->createMany([['id' => 'A']]);
    }

    public function test_a_result_count_that_does_not_match_the_request_cannot_be_mapped(): void
    {
        [$gateway] = $this->gateway($this->json(['ia::result' => [['key' => '1']]]));

        $this->expectException(MappingException::class);

        $gateway->createMany([['id' => 'A'], ['id' => 'B']]);
    }

    public function test_batches_hold_between_1_and_500_records(): void
    {
        $records = array_map(static fn (int $i): array => ['id' => 'C'.$i], range(1, 500));
        [$gateway, $http] = $this->gateway($this->json([
            'ia::result' => array_map(static fn (int $i): array => ['key' => (string) $i], range(1, 500)),
        ]));

        self::assertCount(500, $gateway->createMany($records));

        foreach ([[], [...$records, ['id' => 'C501']]] as $invalid) {
            try {
                $gateway->createMany($invalid);
                self::fail(sprintf('A batch of %d records was accepted.', count($invalid)));
            } catch (InvalidArgument $exception) {
                self::assertStringContainsString('between 1 and 500', $exception->getMessage());
            }
        }

        try {
            $gateway->deleteMany([]);
            self::fail('An empty batch delete was accepted.');
        } catch (InvalidArgument) {
        }

        self::assertCount(1, $http->requests);

        $this->expectException(InvalidArgument::class);
        $gateway->updateMany(array_map(static fn (int $i): array => ['key' => (string) $i], range(1, 501)));
    }

    public function test_empty_records_are_rejected(): void
    {
        [$gateway] = $this->gateway();

        $this->expectException(InvalidArgument::class);

        $gateway->createMany([['id' => 'A'], []]);
    }

    public function test_delete_maps_a_204_without_a_body_to_the_deleted_key(): void
    {
        [$gateway, $http] = $this->gateway(new Response(204));

        $result = $gateway->delete(new ObjectKey('312'));

        self::assertSame('DELETE', $http->requests[0]->getMethod());
        self::assertSame(self::COLLECTION.'/312', (string) $http->requests[0]->getUri());
        self::assertSame('312', $result->reference->key?->value);
        self::assertNull($result->reference->id);
        self::assertSame(0, $result->meta->totalCount);
    }

    public function test_delete_still_maps_a_response_body(): void
    {
        [$gateway] = $this->gateway($this->mutation('312', 'AMoore'));

        $result = $gateway->delete(new ObjectKey('312'));

        self::assertSame('AMoore', $result->reference->id?->value);
        self::assertSame(1, $result->meta->totalSuccess);
    }

    /** @return array{ResourceGateway<\stdClass>, QueueHttpClient} */
    private function gateway(Response ...$responses): array
    {
        $http = new QueueHttpClient(...$responses);
        $factory = new HttpFactory;
        $transport = new ApiTransport(
            new StaticAccessTokenProvider(new AccessToken('token')),
            $http,
            $factory,
            $factory,
        );

        return [
            new ResourceGateway(
                $transport,
                new QueryClient($transport),
                'objects/company-config/contact',
                'company-config/contact',
                static fn (array $data): \stdClass => (object) $data,
            ),
            $http,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): Response
    {
        return new Response(200, [], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function mutation(string $key, string $id = 'ID'): Response
    {
        return $this->json([
            'ia::result' => ['key' => $key, 'id' => $id],
            'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
        ]);
    }
}
