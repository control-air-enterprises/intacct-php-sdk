# Webhooks

[Docs](../README.md) › Webhooks

Namespace: `ControlAir\Intacct\Webhooks`

Sage Intacct pushes record events to your application through **Platform Triggers**. This namespace receives them: it verifies webhook deliveries, reads the event queue, and helps you process each event once.

| Guide | Namespace | Covers |
| --- | --- | --- |
| [Verification](Verification.md) | `Webhooks` | `WebhookVerifier`, `WebhookEvent`, `ClientContext`, `WebhookPayload` |
| [Event queue](EventQueue.md) | `Webhooks\EventQueue` | `EventQueueClient`, `EventBatch`, `QueuedEvent` |
| [Idempotency](Idempotency.md) | `Webhooks\Contracts` | `ProcessedEventStore`, `InMemoryProcessedEventStore` |

## What the SDK does and does not do

Sage has no API for registering webhooks. Triggers are created in the Sage Intacct UI by a company administrator, so the SDK has no `register()` method. It covers everything after a trigger exists:

| Concern | Owner |
| --- | --- |
| Creating, changing, and deleting triggers | Sage Intacct UI (Platform Services) |
| Proving a delivery came from Sage and was not modified | SDK: `WebhookVerifier` |
| Typed access to the body, event metadata, and signature claims | SDK: `WebhookEvent` |
| Fetching events that missed delivery, and event-queue triggers | SDK: `$intacct->eventQueue` |
| Remembering which deliveries were handled | Your storage, through `ProcessedEventStore` |
| HTTP routing, queuing work, and retries of your own jobs | Your application |

## How Sage delivers events

1. A record changes and a trigger's condition matches.
2. Sage renders the trigger's document template into a body and POSTs it to your HTTPS endpoint with three headers:

   | Header | Contents | Signed |
   | --- | --- | --- |
   | `X-IA-Sage-Signature` | An HS256 JWT signed with your OAuth client secret. Its `payload_signature` claim is the SHA-256 hash of the body. | — |
   | `X-ClientContext` | Base64 JSON metadata: company, entity, user, object, event | **No** |
   | `Idempotency-Key` | A unique ID per delivery, repeated on retries | **No** |

3. Any 2xx response is success. HTTP 408, 429, 500, 502, 503, and 504 are retried after 10, 30, and 90 seconds (four attempts in total).
4. A delivery that fails every attempt is kept for 7 days in the [event queue](EventQueue.md).

Your endpoint must be public, use HTTPS, accept POST, and respond quickly. Acknowledge first and do slow work on a background queue.

## Set up a trigger

In Sage Intacct, follow [Add Platform triggers to automate tasks](https://www.intacct.com/ia/docs/en_US/help_action/More/Customization_and_Platform_Services/Triggers/add-platform-triggers.htm), then:

1. Choose the object and the event (for example *After create* on Class).
2. Set the trigger type to **HTTP post** and select **Use webhook delivery**.
3. Enter the **Client ID** of the OAuth application whose client secret your endpoint verifies with.
4. Enter your endpoint URL and a document template. A template that sends the REST object's own field names lets you map the body with the SDK's resource DTOs, for example:

   ```json
   {"key": "{!objects/company-config/class.key!}", "id": "{!objects/company-config/class.id!}", "name": "{!objects/company-config/class.name!}"}
   ```

   This uses the merge-field syntax from Sage's REST trigger guide; pick the fields Sage offers for your object in the template editor.

For an **Event queue** trigger, choose that type instead and enter the client ID. Events then wait in the queue until you [read and acknowledge them](EventQueue.md).

## Quick start

```php
use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\Exceptions\WebhookVerificationException;
use ControlAir\Intacct\Resources\CompanyConfiguration\Classes\ClassDimension;
use ControlAir\Intacct\Webhooks\TriggerEvent;
use ControlAir\Intacct\Webhooks\WebhookVerifier;

$verifier = WebhookVerifier::forApplication(
    new OAuthApplication($_ENV['SAGE_INTACCT_CLIENT_ID'], $_ENV['SAGE_INTACCT_CLIENT_SECRET']),
);

try {
    $event = $verifier->verify($psrServerRequest);
} catch (WebhookVerificationException $exception) {
    $logger->warning('Rejected Sage webhook', ['reason' => $exception->reason->value]);

    return new Response(401);
}

if ($event->context?->restObjectName() === 'company-config/class'
    && $event->context->eventType() === TriggerEvent::AfterCreate) {
    $class = $event->payload->map(ClassDimension::fromArray(...));
}

return new Response(204);
```

Combine this with a [`ProcessedEventStore`](Idempotency.md) so Sage's retries are not processed twice.

## Security notes

- Only the body is signed. Use `X-ClientContext` to route events, but never to make authorization decisions. When in doubt, read the record through the API with its key.
- A valid delivery can be replayed until its JWT expires, which is one hour after it was issued. Storing idempotency keys closes that window for repeated keys.
- Sage's signature header can carry session details alongside the JWT. The verifier extracts only the JWT. Do not log the raw header.
- Webhooks are signed with the OAuth client secret. When you rotate it, update the verifier's secret at the same time: deliveries signed with the other secret fail with `invalid_signature`.
