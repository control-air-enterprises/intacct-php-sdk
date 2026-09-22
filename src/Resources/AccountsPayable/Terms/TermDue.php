<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Terms;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\ArrayReader;

final readonly class TermDue
{
    public function __construct(
        public ?int $days = null,
        public ?TermDateBasis $from = null,
    ) {
        if ($this->days === null && $this->from === null) {
            throw new InvalidArgument('A term due group requires at least one value.');
        }

        if ($this->days !== null && $this->days < 0) {
            throw new InvalidArgument('The term due days cannot be negative.');
        }
    }

    /**
     * Returns null when the group carries no values, as query rows do for unset groups.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $days = ArrayReader::int($data, 'days');
        $from = ArrayReader::string($data, 'from');
        $from = $from === null ? null : TermDateBasis::tryFrom($from);

        if ($days === null && $from === null) {
            return null;
        }

        return new self(days: $days, from: $from);
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return array_filter([
            'days' => $this->days,
            'from' => $this->from?->value,
        ], static fn (int|string|null $value): bool => $value !== null);
    }
}
