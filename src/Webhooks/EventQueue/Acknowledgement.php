<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks\EventQueue;

final readonly class Acknowledgement
{
    public function __construct(
        public string $ackId,
        public ?string $status = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status !== null && strcasecmp($this->status, 'success') === 0;
    }
}
