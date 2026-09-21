<?php

declare(strict_types=1);

namespace ControlAir\Intacct\ValueObjects;

use ControlAir\Intacct\Support\Assert;

final readonly class ObjectKey implements \Stringable
{
    public function __construct(public string $value)
    {
        Assert::notBlank($this->value, 'The object key');
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
