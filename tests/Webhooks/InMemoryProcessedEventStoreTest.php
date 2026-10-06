<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Webhooks;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Webhooks\InMemoryProcessedEventStore;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(InMemoryProcessedEventStore::class)]
final class InMemoryProcessedEventStoreTest extends TestCase
{
    public function test_a_key_can_only_be_claimed_once(): void
    {
        $store = new InMemoryProcessedEventStore;

        self::assertTrue($store->claim('key-1'));
        self::assertFalse($store->claim('key-1'));
        self::assertTrue($store->claim('key-2'));
    }

    public function test_a_released_key_can_be_claimed_again(): void
    {
        $store = new InMemoryProcessedEventStore;
        $store->claim('key-1');

        $store->release('key-1');

        self::assertTrue($store->claim('key-1'));
    }

    public function test_a_claim_expires_after_its_ttl(): void
    {
        $clock = new class implements ClockInterface
        {
            public DateTimeImmutable $now;

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
        $clock->now = new DateTimeImmutable('@1000');
        $store = new InMemoryProcessedEventStore($clock);
        $store->claim('key-1', ttlSeconds: 60);

        $clock->now = new DateTimeImmutable('@1059');
        self::assertFalse($store->claim('key-1', ttlSeconds: 60));

        $clock->now = new DateTimeImmutable('@1060');
        self::assertTrue($store->claim('key-1', ttlSeconds: 60));
    }

    public function test_a_blank_key_is_rejected(): void
    {
        $this->expectException(InvalidArgument::class);

        (new InMemoryProcessedEventStore)->claim(' ');
    }

    public function test_a_non_positive_ttl_is_rejected(): void
    {
        $this->expectException(InvalidArgument::class);

        (new InMemoryProcessedEventStore)->claim('key-1', ttlSeconds: 0);
    }
}
