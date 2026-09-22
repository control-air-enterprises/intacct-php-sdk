<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Http;

use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdempotencyKey::class)]
final class IdempotencyKeyTest extends TestCase
{
    public function test_it_generates_random_version_4_uuids(): void
    {
        $first = IdempotencyKey::generate();
        $second = IdempotencyKey::generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first->value);
        self::assertNotSame($first->value, $second->value);
        self::assertSame(['Idempotency-Key' => $first->value], $first->toHeaders());
    }

    public function test_it_accepts_keys_of_1_to_256_characters(): void
    {
        self::assertSame(256, strlen((new IdempotencyKey(str_repeat('k', 256)))->value));
        self::assertSame('a', (string) new IdempotencyKey('a'));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'too long' => [str_repeat('k', 257)];
        yield 'header injection' => ["abc\r\nX-Evil: 1"];
        yield 'non-ASCII' => ['clé'];
    }

    #[DataProvider('invalidKeys')]
    public function test_it_rejects_invalid_keys(string $value): void
    {
        $this->expectException(InvalidArgument::class);

        new IdempotencyKey($value);
    }
}
