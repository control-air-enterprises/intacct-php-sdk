<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Terms;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;

final readonly class TermDiscount
{
    public function __construct(
        public ?int $days = null,
        public ?TermDateBasis $from = null,
        public ?Decimal $amount = null,
        public ?TermAmountUnit $unit = null,
        public ?int $graceDays = null,
        public ?TermDiscountCalculation $calculateOn = null,
    ) {
        if ($this->days === null && $this->from === null && $this->amount === null
            && $this->unit === null && $this->graceDays === null && $this->calculateOn === null) {
            throw new InvalidArgument('A term discount group requires at least one value.');
        }

        if (($this->days !== null && $this->days < 0) || ($this->graceDays !== null && $this->graceDays < 0)) {
            throw new InvalidArgument('The term discount days cannot be negative.');
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
        $amount = ArrayReader::decimal($data, 'amount');
        $unit = ArrayReader::string($data, 'unit');
        $unit = $unit === null ? null : TermAmountUnit::tryFrom($unit);
        $graceDays = ArrayReader::int($data, 'graceDays');
        $calculateOn = ArrayReader::string($data, 'calculateOn');
        $calculateOn = $calculateOn === null ? null : TermDiscountCalculation::tryFrom($calculateOn);

        if ($days === null && $from === null && $amount === null
            && $unit === null && $graceDays === null && $calculateOn === null) {
            return null;
        }

        return new self(
            days: $days,
            from: $from,
            amount: $amount,
            unit: $unit,
            graceDays: $graceDays,
            calculateOn: $calculateOn,
        );
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return array_filter([
            'days' => $this->days,
            'from' => $this->from?->value,
            'amount' => $this->amount?->value,
            'unit' => $this->unit?->value,
            'graceDays' => $this->graceDays,
            'calculateOn' => $this->calculateOn?->value,
        ], static fn (int|string|null $value): bool => $value !== null);
    }
}
