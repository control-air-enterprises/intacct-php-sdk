# Idempotency

[Docs](../README.md) › [Webhooks](README.md) › Idempotency

Namespace: `ControlAir\Intacct\Webhooks\Contracts`

Sage may deliver the same webhook more than once: it retries on timeouts and 5xx responses, and a delivery can be replayed until its signature expires. Every delivery carries an `Idempotency-Key` header that stays the same across retries. Sage asks receivers to remember these keys for at least 24 hours and skip keys they have already processed.

`ProcessedEventStore` is the contract for that memory. As with `TokenStore`, the SDK defines the contract and your application supplies the storage.

```php
interface ProcessedEventStore
{
    public const RECOMMENDED_TTL_SECONDS = 86_400;

    public function claim(string $key, int $ttlSeconds = self::RECOMMENDED_TTL_SECONDS): bool;

    public function release(string $key): void;
}
```

- `claim()` records the key and returns `true`, or returns `false` if it was already recorded. It must be **atomic**: two workers receiving the same retry at the same moment must not both get `true`.
- `release()` forgets a key so that a delivery that failed to process is handled when Sage retries it.

## Use it in a handler

```php
$event = $verifier->verify($request);
$key = $event->idempotencyKey;

if ($key !== null && ! $store->claim($key)) {
    return new Response(200); // already handled
}

try {
    $handler->handle($event->context, $event->payload);
} catch (\Throwable $exception) {
    if ($key !== null) {
        $store->release($key);
    }

    return new Response(503); // Sage retries
}

return new Response(204);
```

Verify before claiming, so unsigned requests cannot use up keys.

For [queued events](EventQueue.md), claim `$event->eventId` instead.

## Implementations

`InMemoryProcessedEventStore` keeps claims in a PHP array. It is for tests and single-process workers only, because PHP-FPM workers do not share memory.

For production, any storage with an atomic "insert if absent" works.

**Laravel cache**

```php
final class CacheProcessedEventStore implements ProcessedEventStore
{
    public function claim(string $key, int $ttlSeconds = self::RECOMMENDED_TTL_SECONDS): bool
    {
        return Cache::add('intacct-webhook:'.$key, true, $ttlSeconds);
    }

    public function release(string $key): void
    {
        Cache::forget('intacct-webhook:'.$key);
    }
}
```

**Redis**

```php
public function claim(string $key, int $ttlSeconds = self::RECOMMENDED_TTL_SECONDS): bool
{
    return (bool) $this->redis->set('intacct-webhook:'.$key, '1', ['nx', 'ex' => $ttlSeconds]);
}
```

**Relational database:** insert into a table with a unique index on the key, return `false` on a duplicate-key error, and delete expired rows on a schedule.

Sage also suggests returning the previous response for a repeated key. A plain `200` is enough for Sage, which only checks the status class, so the contract stores keys but not responses.
