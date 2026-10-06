<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Exceptions;

use ControlAir\Intacct\Webhooks\VerificationFailure;

/** A webhook request could not be proven to come, unmodified, from Sage Intacct. */
final class WebhookVerificationException extends \RuntimeException implements IntacctException
{
    public function __construct(
        public readonly VerificationFailure $reason,
        ?string $message = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?? $reason->message(), previous: $previous);
    }
}
