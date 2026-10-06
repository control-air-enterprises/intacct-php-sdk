<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time\Timesheets;

use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class CreateTimesheetLine
{
    /**
     * @param  LocalDate  $entryDate  A date inside the timesheet period.
     * @param  Decimal  $quantity  The time worked, in the timesheet's unit of measure.
     * @param  Dimensions|null  $dimensions  The project, task, cost type, customer and other dimensions the time is charged to.
     */
    public function __construct(
        public LocalDate $entryDate,
        public Decimal $quantity,
        public ?ObjectReference $timeType = null,
        public ?Dimensions $dimensions = null,
        public ?bool $billable = null,
        public ?string $description = null,
        public ?string $notes = null,
        public ?ObjectReference $laborClass = null,
        public ?ObjectReference $laborShift = null,
        public ?ObjectReference $laborUnion = null,
        public ?ObjectReference $employeePosition = null,
        public ?TimesheetExternalPayroll $externalPayroll = null,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $dimensions = $this->dimensions?->toWriteArray() ?? [];

        $payload = array_filter([
            'entryDate' => $this->entryDate->value,
            'quantity' => $this->quantity->value,
            'timeType' => $this->timeType?->toWriteArray(),
            'dimensions' => $dimensions === [] ? null : $dimensions,
            'isBillable' => $this->billable,
            'description' => $this->description,
            'notes' => $this->notes,
            'laborClass' => $this->laborClass?->toWriteArray(),
            'laborShift' => $this->laborShift?->toWriteArray(),
            'laborUnion' => $this->laborUnion?->toWriteArray(),
            'employeePosition' => $this->employeePosition?->toWriteArray(),
            'externalPayroll' => $this->externalPayroll?->toWriteArray(),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
