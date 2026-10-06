<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks;

/** A webhook delivery whose signature and body hash have been verified. */
final readonly class WebhookEvent
{
    public function __construct(
        public WebhookPayload $payload,
        public SignatureClaims $claims,
        public ?ClientContext $context = null,
        public ?string $idempotencyKey = null,
    ) {}
}
