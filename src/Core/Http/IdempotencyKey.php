<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Http;

use ControlAir\Intacct\Exceptions\InvalidArgument;

/**
 * The value of the Idempotency-Key header, honoured by Sage Intacct for POST and PATCH.
 *
 * Results are cached for 48 hours and the key is remembered for 60 days. Reusing a key
 * with a different body returns HTTP 409.
 */
final readonly class IdempotencyKey implements \Stringable
{
    public const HEADER = 'Idempotency-Key';

    public const MAX_LENGTH = 256;

    public function __construct(public string $value)
    {
        if (trim($this->value) === '' || strlen($this->value) > self::MAX_LENGTH) {
            throw new InvalidArgument(sprintf(
                'An idempotency key must be between 1 and %d characters.',
                self::MAX_LENGTH,
            ));
        }

        if (preg_match('/^[\x20-\x7E]+$/', $this->value) !== 1) {
            throw new InvalidArgument('An idempotency key may only contain printable ASCII characters.');
        }
    }

    /** Generates a random RFC 4122 version 4 UUID. */
    public static function generate(): self
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return new self(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)));
    }

    /** @return array{Idempotency-Key: string} */
    public function toHeaders(): array
    {
        return [self::HEADER => $this->value];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
