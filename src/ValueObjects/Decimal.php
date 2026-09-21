<?php

declare(strict_types=1);

namespace ControlAir\Intacct\ValueObjects;

use ControlAir\Intacct\Exceptions\InvalidArgument;

final readonly class Decimal implements \Stringable
{
    public function __construct(public string $value)
    {
        if (preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $this->value) !== 1) {
            throw new InvalidArgument(sprintf('"%s" is not a valid decimal value.', $this->value));
        }
    }

    public static function fromInt(int $value): self
    {
        return new self((string) $value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
