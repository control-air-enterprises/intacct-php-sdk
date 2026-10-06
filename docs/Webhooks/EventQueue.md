# Event queue

[Docs](../README.md) › [Webhooks](README.md) › Event queue

Namespace: `ControlAir\Intacct\Webhooks\EventQueue`

The Platform Trigger event queue holds events for your OAuth application until you acknowledge them. It is the pull-based counterpart to webhooks and serves two purposes:

- **Event queue triggers** store every event in the queue instead of POSTing it.
- **Webhook deliveries that fail every retry** are kept in the queue for up to 7 days.

The client is available as `$intacct->eventQueue`. Its calls are ordinary authenticated API requests, so queued events need no signature check.

| Method | Endpoint | Returns |
| --- | --- | --- |
| `count()` | `GET services/delivery/event/count` | `int` |
| `list()` | `GET services/delivery/event/list` | `EventBatch` |
| `acknowledge($batch)` | `POST services/delivery/event/ack` | `Acknowledgement` |

## Drain the queue

```php
for ($round = 0; $round < 100; $round++) {
    $batch = $intacct->eventQueue->list();

    if ($batch->isEmpty()) {
        break;
    }

    foreach ($batch->events as $event) {
        if ($store->claim($event->eventId)) {
            $handler->handle($event->context, $event->payload);
        }
    }

    if (! $intacct->eventQueue->acknowledge($batch)->isSuccessful()) {
        break;
    }
}
```

The round limit and the acknowledgement check stop the loop if Sage keeps returning the same batch. `count()` is useful for monitoring queue depth but is not needed to drain it.

Run this on a schedule, for example every few minutes, to pick up deliveries your endpoint missed.

## Batches and acknowledgement

`list()` returns the next batch without removing it. Acknowledging the batch's `ackId` removes every event in it, so:

- acknowledge only after **every** event in the batch has been handled;
- if your process stops before acknowledging, the same events are returned again, so handle them idempotently;
- `acknowledge()` takes the `EventBatch` or its `ackId` string. It throws `InvalidArgument` for a batch without an `ackId` (an empty queue).

`Acknowledgement::isSuccessful()` is true when Sage reports `status: success`.

Sage's example for the acknowledgement call does not show a request body. The SDK sends `{"ackId": "<id>"}`; confirm this against a sandbox before relying on it in production.

## Queued events

| Property | Type | Notes |
| --- | --- | --- |
| `eventId` | `string` | Unique ID of the event |
| `payload` | `WebhookPayload` | Decoded from base64; the same type a webhook carries |
| `context` | `?ClientContext` | Decoded from base64; null when undecodable |
| `eventType` | `?string` | The delivery type, such as `pull` |

Because the payload and context types match those of a [verified webhook](Verification.md#the-verified-event), one handler can process both sources.

A payload that is not valid base64 throws `MappingException`. A context that cannot be decoded becomes `null`; Sage's own published example context is not valid JSON.
