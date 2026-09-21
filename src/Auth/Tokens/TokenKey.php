<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\Tokens;

use ControlAir\Intacct\Support\Assert;

final readonly class TokenKey
{
    public function __construct(public string $value)
    {
        Assert::notBlank($this->value, 'The token key');
    }
}
