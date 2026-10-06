<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time\Timesheets;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class CreateTimesheet
{
    private const CREATABLE_STATES = [
        TimesheetState::Draft,
        TimesheetState::Saved,
        TimesheetState::Submitted,
    ];

    /**
     * @param  LocalDate  $beginDate  The first day of the timesheet period; Sage derives the end date.
     * @param  list<CreateTimesheetLine>  $lines  At least one line is required.
     * @param  TimesheetState|null  $state  Draft, saved or submitted; Sage defaults to draft.
     */
    public function __construct(
        public ObjectReference $employee,
        public LocalDate $beginDate,
        public array $lines,
        public ?TimesheetState $state = null,
        public ?LocalDate $postingDate = null,
        public ?string $description = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        if ($this->lines === []) {
            throw new InvalidArgument('A timesheet requires at least one line.');
        }

        if ($this->state !== null && ! in_array($this->state, self::CREATABLE_STATES, true)) {
            throw new InvalidArgument(sprintf(
                'A timesheet cannot be created in the "%s" state.',
                $this->state->value,
            ));
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'employee' => $this->employee->toWriteArray(),
            'beginDate' => $this->beginDate->value,
            'state' => $this->state?->value,
            'postingDate' => $this->postingDate?->value,
            'description' => $this->description,
            'lines' => array_map(
                static fn (CreateTimesheetLine $line): array => $line->toArray(),
                $this->lines,
            ),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
