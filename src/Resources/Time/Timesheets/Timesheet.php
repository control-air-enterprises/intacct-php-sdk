<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time\Timesheets;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class Timesheet
{
    /**
     * @param  LocalDate|null  $endDate  Derived by Sage from the begin date and the timesheet period.
     * @param  string|null  $unitOfMeasure  The unit of every line quantity, such as "Hours".
     * @param  list<TimesheetLine>  $lines  Empty on query results; read the timesheet to load its lines.
     */
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?TimesheetState $state,
        public ?ObjectReference $employee,
        public ?LocalDate $beginDate,
        public ?LocalDate $endDate,
        public ?LocalDate $postingDate,
        public ?string $description,
        public ?string $unitOfMeasure,
        public ?Decimal $hoursInDay,
        public array $lines,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $state = ArrayReader::string($data, 'state');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            state: $state === null ? null : TimesheetState::tryFrom($state),
            employee: ArrayReader::reference($data, 'employee'),
            beginDate: ArrayReader::date($data, 'beginDate'),
            endDate: ArrayReader::date($data, 'endDate'),
            postingDate: ArrayReader::date($data, 'postingDate'),
            description: ArrayReader::string($data, 'description'),
            unitOfMeasure: ArrayReader::string($data, 'unitOfMeasure'),
            hoursInDay: ArrayReader::decimal($data, 'hoursInDay'),
            lines: array_map(
                TimesheetLine::fromArray(...),
                ArrayReader::list($data, 'lines'),
            ),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
