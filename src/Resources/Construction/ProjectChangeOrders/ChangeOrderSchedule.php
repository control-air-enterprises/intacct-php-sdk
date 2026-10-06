<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectChangeOrders;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\LocalDate;

/**
 * The `schedule` group shared by project change orders and change requests.
 */
final readonly class ChangeOrderSchedule
{
    /** @var list<string> */
    public const QUERY_FIELDS = [
        'schedule.scheduledStartDate', 'schedule.actualStartDate', 'schedule.scheduledCompletionDate',
        'schedule.revisedCompletionDate', 'schedule.substantialCompletionDate', 'schedule.actualCompletionDate',
        'schedule.noticeToProceedDate', 'schedule.responseDueDate', 'schedule.executedOnDate',
        'schedule.scheduleImpact',
    ];

    public function __construct(
        public ?LocalDate $scheduledStartDate = null,
        public ?LocalDate $actualStartDate = null,
        public ?LocalDate $scheduledCompletionDate = null,
        public ?LocalDate $revisedCompletionDate = null,
        public ?LocalDate $substantialCompletionDate = null,
        public ?LocalDate $actualCompletionDate = null,
        public ?LocalDate $noticeToProceedDate = null,
        public ?LocalDate $responseDueDate = null,
        public ?LocalDate $executedOnDate = null,
        public ?string $scheduleImpact = null,
    ) {}

    /** @param array<string, mixed> $data The `schedule` object. */
    public static function fromArray(array $data): self
    {
        return new self(
            scheduledStartDate: ArrayReader::date($data, 'scheduledStartDate'),
            actualStartDate: ArrayReader::date($data, 'actualStartDate'),
            scheduledCompletionDate: ArrayReader::date($data, 'scheduledCompletionDate'),
            revisedCompletionDate: ArrayReader::date($data, 'revisedCompletionDate'),
            substantialCompletionDate: ArrayReader::date($data, 'substantialCompletionDate'),
            actualCompletionDate: ArrayReader::date($data, 'actualCompletionDate'),
            noticeToProceedDate: ArrayReader::date($data, 'noticeToProceedDate'),
            responseDueDate: ArrayReader::date($data, 'responseDueDate'),
            executedOnDate: ArrayReader::date($data, 'executedOnDate'),
            scheduleImpact: ArrayReader::string($data, 'scheduleImpact'),
        );
    }

    /**
     * Every field of the group, so a PATCH replaces the group and a null clears its field.
     *
     * @return array<string, string|null>
     */
    public function toWriteArray(): array
    {
        return [
            'scheduledStartDate' => $this->scheduledStartDate?->value,
            'actualStartDate' => $this->actualStartDate?->value,
            'scheduledCompletionDate' => $this->scheduledCompletionDate?->value,
            'revisedCompletionDate' => $this->revisedCompletionDate?->value,
            'substantialCompletionDate' => $this->substantialCompletionDate?->value,
            'actualCompletionDate' => $this->actualCompletionDate?->value,
            'noticeToProceedDate' => $this->noticeToProceedDate?->value,
            'responseDueDate' => $this->responseDueDate?->value,
            'executedOnDate' => $this->executedOnDate?->value,
            'scheduleImpact' => $this->scheduleImpact,
        ];
    }

    /**
     * The fields that are set, for a create payload; null when none are.
     *
     * @return array<string, string>|null
     */
    public function toCreateArray(): ?array
    {
        $fields = array_filter($this->toWriteArray(), static fn (?string $value): bool => $value !== null);

        return $fields === [] ? null : $fields;
    }
}
