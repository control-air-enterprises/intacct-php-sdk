<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Webhooks;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\Webhooks\EventQueue\Acknowledgement;
use ControlAir\Intacct\Webhooks\EventQueue\EventBatch;
use ControlAir\Intacct\Webhooks\EventQueue\EventQueueClient;
use ControlAir\Intacct\Webhooks\EventQueue\QueuedEvent;
use ControlAir\Intacct\Webhooks\TriggerEvent;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EventQueueClient::class)]
#[CoversClass(EventBatch::class)]
#[CoversClass(QueuedEvent::class)]
#[CoversClass(Acknowledgement::class)]
final class EventQueueClientTest extends ApiTestCase
{
    private const BASE = 'https://api.intacct.com/ia/api/v1/services/delivery/event/';

    /** Verbatim count example from Sage's trigger queue event services guide. */
    private const COUNT = '{"ia::result":{"count":1},"ia::meta":{"totalCount":1,"totalSuccess":1,"totalError":0}}';

    /** Verbatim list example from the same guide. Its clientContext is not valid JSON. */
    private const LIST = <<<'JSON'
        {"ia::result":{"events":[{"clientContext":"eyAib2JqZWN0IiA6ICJiaWxsIiwgImV2ZW50IiA6ICJhZnRlci5jcmVhdGUiLCAia2V5IiA6ICIxMjM0NSIgOiAiaWQiIDogIkIxMjM0IiB9","contentType":"application/json","eventId":"b61f5df7-cf8d-4e2c-99a1-bb38cddff413","eventType":"pull","payload":"ewogICAgIm9yZGVySWQiOiAiMTIzNDUiLAogICAgImN1c3RvbWVyTmFtZSI6ICJKb2huIERvZSIsCiAgICAidG90YWxBbW91bnQiOiA5OS45OQp9"}],"ackId":"88ae4084-c468-4bbb-92f9-131fc0633292"},"ia::meta":{"totalCount":1,"totalSuccess":1,"totalError":0}}
        JSON;

    /** Verbatim acknowledgement example from the same guide. */
    private const ACK = '{"ia::result":{"ackId":"88ae4084-c468-4bbb-92f9-131fc0633292","status":"success"},"ia::meta":{"totalCount":1,"totalSuccess":1,"totalError":0}}';

    public function test_it_counts_queued_events(): void
    {
        [$client, $http] = $this->client(new Response(200, [], self::COUNT));

        self::assertSame(1, $client->eventQueue->count());
        self::assertSame('GET', $http->requests[0]->getMethod());
        self::assertSame(self::BASE.'count', $this->url($http->requests[0]));
    }

    public function test_it_lists_a_batch_of_events(): void
    {
        [$client, $http] = $this->client(new Response(200, [], self::LIST));

        $batch = $client->eventQueue->list();
        $event = $batch->events[0];

        self::assertSame(self::BASE.'list', $this->url($http->requests[0]));
        self::assertFalse($batch->isEmpty());
        self::assertSame('88ae4084-c468-4bbb-92f9-131fc0633292', $batch->ackId);
        self::assertSame('b61f5df7-cf8d-4e2c-99a1-bb38cddff413', $event->eventId);
        self::assertSame('pull', $event->eventType);
        self::assertSame('application/json', $event->payload->contentType);
        self::assertSame(['orderId' => '12345', 'customerName' => 'John Doe', 'totalAmount' => 99.99], $event->payload->json());
        self::assertNull($event->context, 'The example context is invalid JSON and is ignored.');
    }

    public function test_it_decodes_a_valid_queued_context(): void
    {
        [$client] = $this->client($this->json(['ia::result' => [
            'events' => [[
                'eventId' => 'evt-1',
                'clientContext' => base64_encode('{"object":"bill","event":"after.create","key":"12345"}'),
                'payload' => base64_encode('{"key":"12345"}'),
            ]],
            'ackId' => 'ack-1',
        ]]));

        $event = $client->eventQueue->list()->events[0];

        self::assertNotNull($event->context);
        self::assertSame(TriggerEvent::AfterCreate, $event->context->eventType());
        self::assertSame('12345', $event->context->recordKey());
        self::assertNull($event->payload->contentType);
    }

    public function test_the_live_empty_queue_response_is_an_empty_batch(): void
    {
        // Captured from a live sandbox: an empty queue returns ia::result as an empty list.
        [$client] = $this->client(new Response(200, [], '{"ia::result":[],"ia::meta":{"totalCount":1,"totalSuccess":1,"totalError":0}}'));

        $batch = $client->eventQueue->list();

        self::assertTrue($batch->isEmpty());
        self::assertNull($batch->ackId);
    }

    public function test_an_empty_events_list_returns_an_empty_batch(): void
    {
        [$client] = $this->client($this->json(['ia::result' => ['events' => []]]));

        $batch = $client->eventQueue->list();

        self::assertTrue($batch->isEmpty());
        self::assertNull($batch->ackId);
    }

    public function test_it_acknowledges_a_batch(): void
    {
        [$client, $http] = $this->client(new Response(200, [], self::LIST), new Response(200, [], self::ACK));

        $acknowledgement = $client->eventQueue->acknowledge($client->eventQueue->list());

        self::assertTrue($acknowledgement->isSuccessful());
        self::assertSame('88ae4084-c468-4bbb-92f9-131fc0633292', $acknowledgement->ackId);
        self::assertSame('POST', $http->requests[1]->getMethod());
        self::assertSame(self::BASE.'ack', $this->url($http->requests[1]));
        self::assertSame(['ackId' => '88ae4084-c468-4bbb-92f9-131fc0633292'], $this->jsonBody($http->requests[1]));
    }

    public function test_it_acknowledges_by_id(): void
    {
        [$client] = $this->client($this->json(['ia::result' => ['status' => 'failed']]));

        $acknowledgement = $client->eventQueue->acknowledge('ack-1');

        self::assertSame('ack-1', $acknowledgement->ackId);
        self::assertFalse($acknowledgement->isSuccessful());
    }

    public function test_a_batch_without_an_ack_id_cannot_be_acknowledged(): void
    {
        [$client, $http] = $this->client();

        try {
            $client->eventQueue->acknowledge(new EventBatch([]));
            self::fail('An empty batch was acknowledged.');
        } catch (InvalidArgument) {
            self::assertSame([], $http->requests);
        }
    }

    public function test_a_blank_ack_id_is_rejected(): void
    {
        [$client] = $this->client();

        $this->expectException(InvalidArgument::class);

        $client->eventQueue->acknowledge(' ');
    }

    public function test_an_undecodable_payload_is_a_mapping_error(): void
    {
        [$client] = $this->client($this->json(['ia::result' => ['events' => [['eventId' => 'evt-1', 'payload' => '%%%']]]]));

        $this->expectException(MappingException::class);

        $client->eventQueue->list();
    }

    public function test_a_response_without_a_count_is_a_mapping_error(): void
    {
        [$client] = $this->client($this->json(['ia::result' => []]));

        $this->expectException(MappingException::class);

        $client->eventQueue->count();
    }
}
