<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\Tokens;

use ControlAir\Intacct\Exceptions\InvalidArgument;

final readonly class RefreshToken
{
    public function __construct(
        #[\SensitiveParameter]
        private string $value,
    ) {
        if ($this->value === '') {
            throw new InvalidArgument('The refresh token cannot be empty.');
        }
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
