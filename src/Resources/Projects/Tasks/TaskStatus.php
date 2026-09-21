<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects\Tasks;

use ControlAir\Intacct\Support\Assert;

final readonly class TaskStatus implements \Stringable
{
    public function __construct(public string $value)
    {
        Assert::notBlank($this->value, 'The task status');
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
