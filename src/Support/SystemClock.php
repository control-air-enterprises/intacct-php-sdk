<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Support;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

final readonly class SystemClock implements ClockInterface
{
    public function __construct(private ?DateTimeZone $timezone = null) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
