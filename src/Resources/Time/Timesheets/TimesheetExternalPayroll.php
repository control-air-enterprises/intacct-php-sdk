<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time\Timesheets;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;

/**
 * The `externalPayroll` group of a timesheet line: labor cost and billing figures calculated
 * by an outside payroll system instead of Sage.
 */
final readonly class TimesheetExternalPayroll
{
    public function __construct(
        public ?Decimal $amount = null,
        public ?Decimal $costRate = null,
        public ?Decimal $billingRate = null,
        public ?Decimal $fringes = null,
        public ?Decimal $cashFringes = null,
        public ?Decimal $employerTaxes = null,
    ) {
        if ($this->toWriteArray() === []) {
            throw new InvalidArgument('External payroll values require at least one amount.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $fields = [
            'amount' => ArrayReader::decimal($data, 'amount'),
            'costRate' => ArrayReader::decimal($data, 'costRate'),
            'billingRate' => ArrayReader::decimal($data, 'billingRate'),
            'fringes' => ArrayReader::decimal($data, 'fringes'),
            'cashFringes' => ArrayReader::decimal($data, 'cashFringes'),
            'employerTaxes' => ArrayReader::decimal($data, 'employerTaxes'),
        ];

        if (array_filter($fields, static fn (?Decimal $value): bool => $value !== null) === []) {
            return null;
        }

        return new self(...$fields);
    }

    /**
     * Only the values that are set, so a PATCH leaves the omitted values unchanged.
     *
     * @return array<string, string>
     */
    public function toWriteArray(): array
    {
        return array_filter([
            'amount' => $this->amount?->value,
            'costRate' => $this->costRate?->value,
            'billingRate' => $this->billingRate?->value,
            'fringes' => $this->fringes?->value,
            'cashFringes' => $this->cashFringes?->value,
            'employerTaxes' => $this->employerTaxes?->value,
        ], static fn (?string $value): bool => $value !== null);
    }
}
