<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Support;

use ControlAir\Intacct\Exceptions\InvalidArgument;

final class Assert
{
    public static function notBlank(string $value, string $name): void
    {
        if (trim($value) === '') {
            throw new InvalidArgument(sprintf('%s cannot be empty.', $name));
        }
    }

    public static function absoluteUri(string $value, string $name): void
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgument(sprintf('%s must be an absolute URL.', $name));
        }
    }

    private function __construct() {}
}
