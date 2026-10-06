<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time\Timesheets;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class TimesheetLine
{
    /**
     * @param  Decimal|null  $quantity  The time entered, in the timesheet's unit of measure.
     */
    public function __construct(
        public ObjectKey $key,
        public ?int $lineNumber,
        public ?LocalDate $entryDate,
        public ?Decimal $quantity,
        public ?ObjectReference $timeType,
        public ?TimesheetLineState $state,
        public ?bool $billable,
        public ?string $description,
        public ?string $notes,
        public Dimensions $dimensions,
        public ?ObjectReference $laborClass,
        public ?ObjectReference $laborShift,
        public ?ObjectReference $laborUnion,
        public ?ObjectReference $employeePosition,
        public ?TimesheetExternalPayroll $externalPayroll,
        public TimesheetHours $hours,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $state = ArrayReader::string($data, 'state');

        return new self(
            key: ArrayReader::key($data),
            lineNumber: ArrayReader::int($data, 'lineNumber'),
            entryDate: ArrayReader::date($data, 'entryDate'),
            quantity: ArrayReader::decimal($data, 'quantity'),
            timeType: ArrayReader::reference($data, 'timeType'),
            state: $state === null ? null : TimesheetLineState::tryFrom($state),
            billable: ArrayReader::bool($data, 'isBillable'),
            description: ArrayReader::string($data, 'description'),
            notes: ArrayReader::string($data, 'notes'),
            dimensions: Dimensions::fromArray(ArrayReader::object($data['dimensions'] ?? null) ?? []),
            laborClass: ArrayReader::reference($data, 'laborClass'),
            laborShift: ArrayReader::reference($data, 'laborShift'),
            laborUnion: ArrayReader::reference($data, 'laborUnion'),
            employeePosition: ArrayReader::reference($data, 'employeePosition'),
            externalPayroll: TimesheetExternalPayroll::fromArray(ArrayReader::object($data['externalPayroll'] ?? null) ?? []),
            hours: TimesheetHours::fromArray(ArrayReader::object($data['hours'] ?? null) ?? []),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
