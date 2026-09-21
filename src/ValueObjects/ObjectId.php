<?php

declare(strict_types=1);

namespace ControlAir\Intacct\ValueObjects;

use ControlAir\Intacct\Support\Assert;

final readonly class ObjectId implements \Stringable
{
    public function __construct(public string $value)
    {
        Assert::notBlank($this->value, 'The object ID');
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
