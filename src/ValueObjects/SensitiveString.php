<?php

declare(strict_types=1);

namespace ControlAir\Intacct\ValueObjects;

use ControlAir\Intacct\Support\Assert;

final readonly class SensitiveString
{
    public function __construct(
        #[\SensitiveParameter]
        private string $value,
    ) {
        Assert::notBlank($this->value, 'The sensitive value');
    }

    public function reveal(): string
    {
        return $this->value;
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }
}
