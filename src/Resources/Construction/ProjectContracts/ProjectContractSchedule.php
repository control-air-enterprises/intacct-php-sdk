<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContracts;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\LocalDate;

/** The `schedule` dates of a project contract or contract line. */
final readonly class ProjectContractSchedule
{
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

    /** @param array<string, mixed> $data */
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
     * The fields that are set; a null field is left out, so a PATCH leaves it unchanged.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
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
        ], static fn (?string $value): bool => $value !== null);
    }
}
