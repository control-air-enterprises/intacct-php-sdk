<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks\EventQueue;

/** A batch of queued events. Acknowledge it by its ackId once every event is processed. */
final readonly class EventBatch
{
    /** @param list<QueuedEvent> $events */
    public function __construct(
        public array $events,
        public ?string $ackId = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->events === [];
    }
}
