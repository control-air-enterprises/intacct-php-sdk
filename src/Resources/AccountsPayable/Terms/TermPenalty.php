<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Terms;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;

final readonly class TermPenalty
{
    public function __construct(
        public ?TermPenaltyCycle $cycle = null,
        public ?Decimal $amount = null,
        public ?TermAmountUnit $unit = null,
        public ?int $graceDays = null,
    ) {
        if ($this->cycle === null && $this->amount === null && $this->unit === null && $this->graceDays === null) {
            throw new InvalidArgument('A term penalty group requires at least one value.');
        }

        if ($this->graceDays !== null && $this->graceDays < 0) {
            throw new InvalidArgument('The term penalty grace days cannot be negative.');
        }
    }

    /**
     * Returns null when the group carries no values, as query rows do for unset groups.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $cycle = ArrayReader::string($data, 'cycle');
        $cycle = $cycle === null ? null : TermPenaltyCycle::tryFrom($cycle);
        $amount = ArrayReader::decimal($data, 'amount');
        $unit = ArrayReader::string($data, 'unit');
        $unit = $unit === null ? null : TermAmountUnit::tryFrom($unit);
        $graceDays = ArrayReader::int($data, 'graceDays');

        if ($cycle === null && $amount === null && $unit === null && $graceDays === null) {
            return null;
        }

        return new self(cycle: $cycle, amount: $amount, unit: $unit, graceDays: $graceDays);
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return array_filter([
            'cycle' => $this->cycle?->value,
            'amount' => $this->amount?->value,
            'unit' => $this->unit?->value,
            'graceDays' => $this->graceDays,
        ], static fn (int|string|null $value): bool => $value !== null);
    }
}
