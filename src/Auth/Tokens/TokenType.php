<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\Tokens;

enum TokenType: string
{
    case Bearer = 'Bearer';

    public static function fromResponse(string $value): self
    {
        return match (strtolower($value)) {
            'bearer' => self::Bearer,
            default => throw new \ValueError(sprintf('Unsupported OAuth token type "%s".', $value)),
        };
    }
}
