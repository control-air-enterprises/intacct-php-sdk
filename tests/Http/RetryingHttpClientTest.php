<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Http;

use ControlAir\Intacct\Core\Http\RetryingHttpClient;
use ControlAir\Intacct\Core\Http\RetryPolicy;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Tests\Support\BodyReadingHttpClient;
use ControlAir\Intacct\Tests\Support\FrozenClock;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\Tests\Support\RecordingSleeper;
use DateTimeImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

#[CoversClass(RetryingHttpClient::class)]
#[CoversClass(RetryPolicy::class)]
final class RetryingHttpClientTest extends TestCase
{
    private const URI = 'https://example.test/ia/api/v1/objects/purchasing/document';

    private RecordingSleeper $sleeper;

    protected function setUp(): void
    {
        $this->sleeper = new RecordingSleeper;
    }

    public function test_it_retries_a_rate_limited_post(): void
    {
        $http = new QueueHttpClient(new Response(429), new Response(201));

        $response = $this->client($http)->sendRequest(new Request('POST', self::URI, [], '{"id":"PO-1"}'));

        self::assertSame(201, $response->getStatusCode());
        self::assertCount(2, $http->requests);
        self::assertSame([100], $this->sleeper->delays);
    }

    public function test_it_honors_retry_after_in_seconds(): void
    {
        $http = new QueueHttpClient(new Response(429, ['Retry-After' => '3']), new Response(200));

        $this->client($http)->sendRequest(new Request('POST', self::URI));

        self::assertSame([3000], $this->sleeper->delays);
    }

    public function test_it_falls_back_to_sage_throttle_headers(): void
    {
        $http = new QueueHttpClient(
            new Response(429, ['X-IA-Throttle-Limit-Retry-After' => '2']),
            new Response(429, ['X-IA-Hour-Rate-Limit-Retry-After' => '4', 'Retry-After' => '1']),
            new Response(200),
        );

        $this->client($http)->sendRequest(new Request('POST', self::URI));

        self::assertSame([2000, 1000], $this->sleeper->delays);
    }

    public function test_it_honors_retry_after_as_an_http_date(): void
    {
        $http = new QueueHttpClient(
            new Response(429, ['Retry-After' => 'Tue, 22 Sep 2026 12:00:07 GMT']),
            new Response(200),
        );

        $this->client($http)->sendRequest(new Request('GET', self::URI));

        self::assertSame([7000], $this->sleeper->delays);
    }

    public function test_it_accepts_obsolete_http_date_formats(): void
    {
        $http = new QueueHttpClient(
            new Response(429, ['Retry-After' => 'Tuesday, 22-Sep-26 12:00:04 GMT']),
            new Response(429, ['Retry-After' => 'Tue Sep 22 12:00:05 2026']),
            new Response(200),
        );

        $this->client($http)->sendRequest(new Request('GET', self::URI));

        self::assertSame([4000, 5000], $this->sleeper->delays);
    }

    public function test_it_does_not_wait_for_a_retry_after_date_in_the_past(): void
    {
        $http = new QueueHttpClient(
            new Response(429, ['Retry-After' => 'Tue, 22 Sep 2026 11:59:00 GMT']),
            new Response(200),
        );

        $this->client($http)->sendRequest(new Request('GET', self::URI));

        self::assertSame([0], $this->sleeper->delays);
    }

    public function test_it_caps_retry_after_at_the_maximum_delay(): void
    {
        $http = new QueueHttpClient(
            new Response(429, ['Retry-After' => '3600']),
            new Response(429, ['Retry-After' => 'Wed, 23 Sep 2026 12:00:00 GMT']),
            new Response(429, ['Retry-After' => '99999999999999999999999']),
            new Response(200),
        );

        $this->client($http)->sendRequest(new Request('GET', self::URI));

        self::assertSame([10_000, 10_000, 10_000], $this->sleeper->delays);
    }

    public function test_it_falls_back_to_backoff_when_retry_after_is_invalid(): void
    {
        $http = new QueueHttpClient(new Response(429, ['Retry-After' => 'soon']), new Response(200));

        $this->client($http)->sendRequest(new Request('GET', self::URI));

        self::assertSame([100], $this->sleeper->delays);
    }

    #[DataProvider('idempotentMethods')]
    public function test_it_retries_server_errors_for_idempotent_methods(string $method): void
    {
        $http = new QueueHttpClient(new Response(503), new Response(502), new Response(200));

        $response = $this->client($http)->sendRequest(new Request($method, self::URI));

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(3, $http->requests);
        self::assertSame([100, 200], $this->sleeper->delays);
    }

    public function test_it_honors_retry_after_on_a_retried_server_error(): void
    {
        $http = new QueueHttpClient(new Response(503, ['Retry-After' => '2']), new Response(200));

        $this->client($http)->sendRequest(new Request('GET', self::URI));

        self::assertSame([2000], $this->sleeper->delays);
    }

    #[DataProvider('nonIdempotentMethods')]
    public function test_it_never_retries_server_errors_for_non_idempotent_methods(string $method): void
    {
        $http = new QueueHttpClient(new Response(503), new Response(200));

        $response = $this->client($http)->sendRequest(new Request($method, self::URI, [], '{"id":"PO-1"}'));

        self::assertSame(503, $response->getStatusCode());
        self::assertCount(1, $http->requests);
        self::assertSame([], $this->sleeper->delays);
    }

    #[DataProvider('nonIdempotentMethods')]
    public function test_it_retries_server_errors_for_requests_with_an_idempotency_key(string $method): void
    {
        $http = new QueueHttpClient(new Response(503), new Response(200));
        $request = new Request($method, self::URI, ['Idempotency-Key' => 'po-1'], '{"id":"PO-1"}');

        $response = $this->client($http)->sendRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(2, $http->requests);
    }

    public function test_it_retries_a_network_error_for_a_post_with_an_idempotency_key(): void
    {
        $request = new Request('POST', self::URI, ['Idempotency-Key' => 'po-1'], '{"id":"PO-1"}');
        $http = new QueueHttpClient(new ConnectException('Connection reset', $request), new Response(201));

        $response = $this->client($http)->sendRequest($request);

        self::assertSame(201, $response->getStatusCode());
        self::assertCount(2, $http->requests);
    }

    #[DataProvider('nonRetryableStatuses')]
    public function test_it_returns_non_retryable_responses_immediately(int $status): void
    {
        $http = new QueueHttpClient(new Response($status), new Response(200));

        $response = $this->client($http)->sendRequest(new Request('GET', self::URI));

        self::assertSame($status, $response->getStatusCode());
        self::assertCount(1, $http->requests);
    }

    public function test_it_retries_a_network_error_for_get(): void
    {
        $request = new Request('GET', self::URI);
        $http = new QueueHttpClient(new ConnectException('Connection reset.', $request), new Response(200));

        $response = $this->client($http)->sendRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(2, $http->requests);
        self::assertSame([100], $this->sleeper->delays);
    }

    #[DataProvider('nonIdempotentMethods')]
    public function test_it_never_retries_a_network_error_for_non_idempotent_methods(string $method): void
    {
        $request = new Request($method, self::URI, [], '{"id":"PO-1"}');
        $exception = new ConnectException('Operation timed out.', $request);
        $http = new QueueHttpClient($exception, new Response(200));

        try {
            $this->client($http)->sendRequest($request);
            self::fail('The network exception was not rethrown.');
        } catch (ConnectException $caught) {
            self::assertSame($exception, $caught);
        }

        self::assertCount(1, $http->requests);
        self::assertSame([], $this->sleeper->delays);
    }

    public function test_it_does_not_retry_client_errors_that_are_not_network_errors(): void
    {
        $request = new Request('GET', self::URI);
        $http = new QueueHttpClient(new RequestException('Malformed request.', $request), new Response(200));

        $this->expectException(RequestException::class);

        try {
            $this->client($http)->sendRequest($request);
        } finally {
            self::assertCount(1, $http->requests);
        }
    }

    public function test_it_rethrows_the_last_network_error_after_max_attempts(): void
    {
        $request = new Request('GET', self::URI);
        $last = new ConnectException('Third failure.', $request);
        $http = new QueueHttpClient(
            new ConnectException('First failure.', $request),
            new ConnectException('Second failure.', $request),
            $last,
            new Response(200),
        );

        try {
            $this->client($http, new RetryPolicy(maxRetries: 2, baseDelayMilliseconds: 100, jitter: 0.0))
                ->sendRequest($request);
            self::fail('The network exception was not rethrown.');
        } catch (ConnectException $caught) {
            self::assertSame($last, $caught);
        }

        self::assertCount(3, $http->requests);
        self::assertSame([100, 200], $this->sleeper->delays);
    }

    public function test_it_stops_after_max_attempts_and_returns_the_last_response(): void
    {
        $last = new Response(429, [], 'last');
        $http = new QueueHttpClient(new Response(429), new Response(429), new Response(429), $last, new Response(200));

        $response = $this->client($http)->sendRequest(new Request('POST', self::URI));

        self::assertSame($last, $response);
        self::assertCount(4, $http->requests);
        self::assertSame([100, 200, 400], $this->sleeper->delays);
    }

    public function test_it_caps_exponential_backoff_at_the_maximum_delay(): void
    {
        $http = new QueueHttpClient(...array_fill(0, 6, new Response(500)));
        $policy = new RetryPolicy(maxRetries: 5, baseDelayMilliseconds: 100, maxDelayMilliseconds: 500, jitter: 0.0);

        $response = $this->client($http, $policy)->sendRequest(new Request('GET', self::URI));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame([100, 200, 400, 500, 500], $this->sleeper->delays);
    }

    public function test_it_does_not_retry_when_retries_are_disabled(): void
    {
        $http = new QueueHttpClient(new Response(429), new Response(200));

        $response = $this->client($http, new RetryPolicy(maxRetries: 0))->sendRequest(new Request('GET', self::URI));

        self::assertSame(429, $response->getStatusCode());
        self::assertSame([], $this->sleeper->delays);
    }

    public function test_it_replays_the_request_body_on_retry(): void
    {
        $body = '{"id":"PO-1","vendor":{"id":"V-100"}}';
        $http = new BodyReadingHttpClient(new QueueHttpClient(new Response(429), new Response(429), new Response(201)));
        $request = new Request('POST', self::URI, ['Content-Type' => 'application/json'], $body);

        $response = $this->client($http)->sendRequest($request);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame([$body, $body, $body], $http->bodies);
    }

    public function test_it_does_not_retry_when_the_body_cannot_be_replayed(): void
    {
        $http = new QueueHttpClient(new Response(429), new Response(200));
        $request = new Request('POST', self::URI, [], new NoSeekStream(Utils::streamFor('{"id":"PO-1"}')));

        $response = $this->client($http)->sendRequest($request);

        self::assertSame(429, $response->getStatusCode());
        self::assertCount(1, $http->requests);
        self::assertSame([], $this->sleeper->delays);
    }

    public function test_it_applies_jitter_within_the_configured_fraction(): void
    {
        $http = new QueueHttpClient(new Response(429), new Response(429), new Response(429), new Response(200));
        $client = new RetryingHttpClient(
            client: $http,
            policy: new RetryPolicy(baseDelayMilliseconds: 1000, maxDelayMilliseconds: 10_000, jitter: 0.5),
            sleeper: $this->sleeper,
            clock: $this->clock(),
            randomizer: new Randomizer(new Xoshiro256StarStar(42)),
        );

        $client->sendRequest(new Request('GET', self::URI));

        self::assertCount(3, $this->sleeper->delays);

        foreach ([1000, 2000, 4000] as $index => $backoff) {
            self::assertGreaterThanOrEqual($backoff / 2, $this->sleeper->delays[$index]);
            self::assertLessThanOrEqual($backoff, $this->sleeper->delays[$index]);
        }
    }

    /**
     * @param  array{maxRetries?: int, baseDelayMilliseconds?: int, maxDelayMilliseconds?: int, jitter?: float}  $arguments
     */
    #[DataProvider('invalidPolicies')]
    public function test_it_rejects_an_invalid_policy(array $arguments, string $message): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage($message);

        new RetryPolicy(...$arguments);
    }

    /** @return iterable<string, array{string}> */
    public static function idempotentMethods(): iterable
    {
        yield 'GET' => ['GET'];
        yield 'HEAD' => ['HEAD'];
        yield 'OPTIONS' => ['OPTIONS'];
        yield 'DELETE' => ['DELETE'];
        yield 'lowercase get' => ['get'];
    }

    /** @return iterable<string, array{string}> */
    public static function nonIdempotentMethods(): iterable
    {
        yield 'POST' => ['POST'];
        yield 'PATCH' => ['PATCH'];
    }

    /** @return iterable<string, array{int}> */
    public static function nonRetryableStatuses(): iterable
    {
        yield '400' => [400];
        yield '401' => [401];
        yield '404' => [404];
        yield '501' => [501];
        yield '505' => [505];
    }

    /** @return iterable<string, array{array{maxRetries?: int, baseDelayMilliseconds?: int, maxDelayMilliseconds?: int, jitter?: float}, string}> */
    public static function invalidPolicies(): iterable
    {
        yield 'negative retries' => [['maxRetries' => -1], 'cannot be negative'];
        yield 'negative base delay' => [['baseDelayMilliseconds' => -1], 'cannot be negative'];
        yield 'max below base' => [['baseDelayMilliseconds' => 1000, 'maxDelayMilliseconds' => 999], 'cannot be less than'];
        yield 'negative jitter' => [['jitter' => -0.1], 'between 0 and 1'];
        yield 'jitter above one' => [['jitter' => 1.5], 'between 0 and 1'];
    }

    private function client(ClientInterface $http, ?RetryPolicy $policy = null): RetryingHttpClient
    {
        return new RetryingHttpClient(
            client: $http,
            policy: $policy ?? new RetryPolicy(baseDelayMilliseconds: 100, maxDelayMilliseconds: 10_000, jitter: 0.0),
            sleeper: $this->sleeper,
            clock: $this->clock(),
        );
    }

    private function clock(): FrozenClock
    {
        return new FrozenClock(new DateTimeImmutable('2026-09-22 12:00:00', new \DateTimeZone('UTC')));
    }
}
