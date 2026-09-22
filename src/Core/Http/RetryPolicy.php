<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Http;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use Random\Randomizer;

final readonly class RetryPolicy
{
    /**
     * Methods that may be retried after a 5xx response or a network error.
     */
    public const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'OPTIONS', 'DELETE'];

    /**
     * Server errors that may be retried for idempotent methods.
     */
    public const RETRYABLE_SERVER_ERRORS = [500, 502, 503, 504];

    public const TOO_MANY_REQUESTS = 429;

    /**
     * @param  int  $maxRetries  Retries after the first attempt; 0 disables retrying.
     * @param  int  $baseDelayMilliseconds  Delay before the first retry, doubled for each later retry.
     * @param  int  $maxDelayMilliseconds  Upper bound for any single delay, including Retry-After.
     * @param  float  $jitter  Fraction (0-1) of the backoff delay that is randomly removed.
     */
    public function __construct(
        public int $maxRetries = 3,
        public int $baseDelayMilliseconds = 500,
        public int $maxDelayMilliseconds = 30_000,
        public float $jitter = 0.5,
    ) {
        if ($this->maxRetries < 0) {
            throw new InvalidArgument('The maximum number of retries cannot be negative.');
        }

        if ($this->baseDelayMilliseconds < 0) {
            throw new InvalidArgument('The base retry delay cannot be negative.');
        }

        if ($this->maxDelayMilliseconds < $this->baseDelayMilliseconds) {
            throw new InvalidArgument('The maximum retry delay cannot be less than the base retry delay.');
        }

        if ($this->jitter < 0.0 || $this->jitter > 1.0) {
            throw new InvalidArgument('The retry jitter must be between 0 and 1.');
        }
    }

    public function isIdempotent(string $method): bool
    {
        return in_array(strtoupper($method), self::IDEMPOTENT_METHODS, true);
    }

    public function isRetryableStatus(string $method, int $statusCode): bool
    {
        if ($statusCode === self::TOO_MANY_REQUESTS) {
            return true;
        }

        return in_array($statusCode, self::RETRYABLE_SERVER_ERRORS, true) && $this->isIdempotent($method);
    }

    /**
     * Exponential backoff with jitter for the given retry (1 = first retry), capped by the maximum delay.
     */
    public function backoffDelay(int $retry, Randomizer $randomizer = new Randomizer): int
    {
        $exponential = $this->baseDelayMilliseconds * 2.0 ** max(0, $retry - 1);
        $delay = (int) min($this->maxDelayMilliseconds, $exponential);
        $spread = (int) floor($delay * $this->jitter);

        return $spread > 0 ? $delay - $randomizer->getInt(0, $spread) : $delay;
    }

    public function capDelay(int $milliseconds): int
    {
        return max(0, min($this->maxDelayMilliseconds, $milliseconds));
    }
}
