<?php

declare(strict_types=1);

namespace ControlAir\Intacct\ValueObjects;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use DateTimeImmutable;

final readonly class LocalDate implements \Stringable
{
    public function __construct(public string $value)
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $this->value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgument(sprintf('"%s" is not a valid YYYY-MM-DD date.', $this->value));
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
