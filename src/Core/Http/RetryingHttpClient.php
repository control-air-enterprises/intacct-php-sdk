<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Http;

use ControlAir\Intacct\Support\Sleeper;
use ControlAir\Intacct\Support\SystemClock;
use ControlAir\Intacct\Support\SystemSleeper;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Random\Randomizer;

/**
 * Opt-in PSR-18 decorator that retries rate-limited requests and transient failures.
 *
 * - HTTP 429 is retried for every method, honoring Retry-After.
 * - HTTP 500/502/503/504 and network errors are retried only for GET, HEAD, OPTIONS and DELETE,
 *   so a POST or PATCH that may already have been processed is never sent twice.
 * - Requests whose body cannot be rewound are never retried.
 */
final readonly class RetryingHttpClient implements ClientInterface
{
    /**
     * IMF-fixdate followed by the obsolete RFC 850 and asctime() forms (RFC 9110, section 5.6.7).
     */
    private const HTTP_DATE_FORMATS = [
        '!D, d M Y H:i:s \G\M\T',
        '!l, d-M-y H:i:s \G\M\T',
        '!D M j H:i:s Y',
    ];

    public function __construct(
        private ClientInterface $client,
        private RetryPolicy $policy = new RetryPolicy,
        private Sleeper $sleeper = new SystemSleeper,
        private ClockInterface $clock = new SystemClock,
        private Randomizer $randomizer = new Randomizer,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $retry = 0;

        while (true) {
            try {
                $response = $this->client->sendRequest($request);
            } catch (NetworkExceptionInterface $exception) {
                if (! $this->policy->isIdempotent($request->getMethod()) || ! $this->canRetry($request, $retry)) {
                    throw $exception;
                }

                $this->pause(++$retry, null);

                continue;
            }

            if (! $this->policy->isRetryableStatus($request->getMethod(), $response->getStatusCode())
                || ! $this->canRetry($request, $retry)) {
                return $response;
            }

            $this->pause(++$retry, $response);
        }
    }

    /**
     * Checks the retry budget and rewinds the request body so it can be replayed.
     */
    private function canRetry(RequestInterface $request, int $retry): bool
    {
        if ($retry >= $this->policy->maxRetries) {
            return false;
        }

        $body = $request->getBody();

        if (! $body->isSeekable()) {
            return false;
        }

        $body->rewind();

        return true;
    }

    private function pause(int $retry, ?ResponseInterface $response): void
    {
        $delay = $response !== null ? $this->retryAfter($response) : null;

        $this->sleeper->sleep($delay ?? $this->policy->backoffDelay($retry, $this->randomizer));
    }

    /**
     * Returns the Retry-After delay in milliseconds, capped by the policy, or null when absent or invalid.
     */
    private function retryAfter(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            // Compare in seconds first so very large values cannot overflow when converted.
            $maxSeconds = intdiv($this->policy->maxDelayMilliseconds, 1000) + 1;

            return $this->policy->capDelay(min((int) $value, $maxSeconds) * 1000);
        }

        $date = $this->parseHttpDate($value);

        if ($date === null) {
            return null;
        }

        $now = $this->clock->now();
        $seconds = (float) $date->format('U.u') - (float) $now->format('U.u');
        $seconds = min($seconds, $this->policy->maxDelayMilliseconds / 1000);

        return $this->policy->capDelay((int) ceil($seconds * 1000));
    }

    private function parseHttpDate(string $value): ?DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');

        foreach (self::HTTP_DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $utc);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date;
            }
        }

        return null;
    }
}
