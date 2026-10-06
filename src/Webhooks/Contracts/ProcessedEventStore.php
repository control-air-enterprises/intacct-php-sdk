<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks\Contracts;

/**
 * Remembers which webhook deliveries were already handled, keyed by their
 * Idempotency-Key, so Sage's retries are not processed twice.
 *
 * Implement it with storage shared by every worker that receives webhooks, such as
 * Redis `SET NX EX`, Laravel's `Cache::add()`, or a unique database column.
 */
interface ProcessedEventStore
{
    /** Sage asks receivers to keep idempotency keys for at least 24 hours. */
    public const RECOMMENDED_TTL_SECONDS = 86_400;

    /**
     * Records the key unless it is already recorded, atomically.
     *
     * @return bool true when this call claimed the key, false when it was already claimed
     */
    public function claim(string $key, int $ttlSeconds = self::RECOMMENDED_TTL_SECONDS): bool;

    /** Forgets a claim, so a delivery that failed to process is handled when Sage retries it. */
    public function release(string $key): void;
}
