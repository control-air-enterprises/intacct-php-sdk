<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks\EventQueue;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\HttpMethod;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\Support\Assert;

/**
 * The Platform Trigger event queue (`services/delivery/event`).
 *
 * Event-queue triggers store events for the calling OAuth application, and webhook
 * deliveries that exhaust their retries are kept for up to 7 days. Events stay queued
 * until their batch is acknowledged.
 */
final readonly class EventQueueClient
{
    public function __construct(private ApiTransport $transport) {}

    /** The number of events waiting for this application. */
    public function count(): int
    {
        $result = $this->result($this->transport->request(HttpMethod::Get, 'services/delivery/event/count'));

        return ArrayReader::int($result, 'count')
            ?? throw new MappingException('The event count response does not contain a count.');
    }

    /** Reads the next batch of events without removing them from the queue. */
    public function list(): EventBatch
    {
        $payload = $this->transport->request(HttpMethod::Get, 'services/delivery/event/list');

        // An empty queue is returned as `"ia::result": []` rather than an object.
        if (($payload['ia::result'] ?? null) === []) {
            return new EventBatch([]);
        }

        $result = $this->result($payload);
        $events = $result['events'] ?? [];

        if (! is_array($events) || ! array_is_list($events)) {
            throw new MappingException('The event list response does not contain an events list.');
        }

        return new EventBatch(
            events: array_map(static fn (mixed $event): QueuedEvent => QueuedEvent::fromArray(
                ArrayReader::object($event) ?? throw new MappingException('A queued event is not an object.'),
            ), $events),
            ackId: ArrayReader::string($result, 'ackId'),
        );
    }

    /**
     * Removes a processed batch from the queue. Acknowledge only after every event in
     * the batch has been handled; unacknowledged events are returned again.
     *
     * Sage's example omits the request body; the SDK sends `{"ackId": "..."}`.
     */
    public function acknowledge(EventBatch|string $batch): Acknowledgement
    {
        $ackId = $batch instanceof EventBatch
            ? ($batch->ackId ?? throw new InvalidArgument('The event batch has no ackId to acknowledge.'))
            : $batch;

        Assert::notBlank($ackId, 'The event acknowledgement ID');

        $result = $this->result($this->transport->request(
            HttpMethod::Post,
            'services/delivery/event/ack',
            json: ['ackId' => $ackId],
        ));

        return new Acknowledgement(
            ackId: ArrayReader::string($result, 'ackId') ?? $ackId,
            status: ArrayReader::string($result, 'status'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function result(array $payload): array
    {
        return ArrayReader::object($payload['ia::result'] ?? null)
            ?? throw new MappingException('The event queue response does not contain an ia::result object.');
    }
}
