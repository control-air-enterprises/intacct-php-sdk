<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\Support\SystemClock;
use ControlAir\Intacct\Webhooks\Contracts\ProcessedEventStore;
use Psr\Clock\ClockInterface;

/**
 * A per-process store for tests and single-process workers only. It is not shared
 * between PHP-FPM workers or servers, so it cannot deduplicate in production.
 */
final class InMemoryProcessedEventStore implements ProcessedEventStore
{
    /** @var array<string, int> key => expiry timestamp */
    private array $claims = [];

    public function __construct(private readonly ClockInterface $clock = new SystemClock) {}

    public function claim(string $key, int $ttlSeconds = self::RECOMMENDED_TTL_SECONDS): bool
    {
        Assert::notBlank($key, 'The idempotency key');

        if ($ttlSeconds < 1) {
            throw new InvalidArgument('The idempotency key TTL must be at least one second.');
        }

        $now = $this->clock->now()->getTimestamp();

        if (isset($this->claims[$key]) && $this->claims[$key] > $now) {
            return false;
        }

        $this->claims[$key] = $now + $ttlSeconds;

        return true;
    }

    public function release(string $key): void
    {
        unset($this->claims[$key]);
    }
}
