<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks\EventQueue;

use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\Support\Base64;
use ControlAir\Intacct\Webhooks\ClientContext;
use ControlAir\Intacct\Webhooks\WebhookPayload;

/**
 * A trigger event read from the event queue. It arrives over an authenticated API call,
 * so unlike a webhook it needs no signature check.
 */
final readonly class QueuedEvent
{
    public function __construct(
        public string $eventId,
        public WebhookPayload $payload,
        public ?ClientContext $context = null,
        public ?string $eventType = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $encoded = ArrayReader::string($data, 'payload');
        $payload = $encoded === null ? '' : Base64::decode($encoded);

        if ($payload === null) {
            throw new MappingException('A queued event payload is not valid base64.');
        }

        return new self(
            eventId: ArrayReader::requiredString($data, 'eventId'),
            payload: new WebhookPayload($payload, ArrayReader::string($data, 'contentType')),
            context: ClientContext::tryFromEncoded(ArrayReader::string($data, 'clientContext')),
            eventType: ArrayReader::string($data, 'eventType'),
        );
    }
}
