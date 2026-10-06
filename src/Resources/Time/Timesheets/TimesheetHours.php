<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time\Timesheets;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;

/**
 * The read-only `hours` group of a timesheet line, which Sage derives from the quantity,
 * the billable flag and the approval state.
 */
final readonly class TimesheetHours
{
    public function __construct(
        public ?Decimal $billable = null,
        public ?Decimal $nonBillable = null,
        public ?Decimal $utilized = null,
        public ?Decimal $nonUtilized = null,
        public ?Decimal $approved = null,
        public ?Decimal $approvedBillable = null,
        public ?Decimal $approvedNonBillable = null,
        public ?Decimal $approvedUtilized = null,
        public ?Decimal $approvedNonUtilized = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            billable: ArrayReader::decimal($data, 'billable'),
            nonBillable: ArrayReader::decimal($data, 'nonBillable'),
            utilized: ArrayReader::decimal($data, 'utilized'),
            nonUtilized: ArrayReader::decimal($data, 'nonUtilized'),
            approved: ArrayReader::decimal($data, 'approved'),
            approvedBillable: ArrayReader::decimal($data, 'approvedBillable'),
            approvedNonBillable: ArrayReader::decimal($data, 'approvedNonBillable'),
            approvedUtilized: ArrayReader::decimal($data, 'approvedUtilized'),
            approvedNonUtilized: ArrayReader::decimal($data, 'approvedNonUtilized'),
        );
    }
}
