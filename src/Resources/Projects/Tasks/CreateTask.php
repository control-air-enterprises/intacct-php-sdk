<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects\Tasks;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class CreateTask
{
    public function __construct(
        public ObjectId $id,
        public string $name,
        public ObjectReference $project,
        public ?string $description = null,
        public ?ObjectReference $parent = null,
        public ?TaskStatus $status = null,
        public ?LocalDate $plannedStartDate = null,
        public ?LocalDate $plannedEndDate = null,
        public ?bool $isMilestone = null,
        public ?bool $isUtilized = null,
        public ?bool $isBillable = null,
        public ?ObjectReference $item = null,
        public ?ObjectReference $timeType = null,
        public ?ObjectReference $class = null,
        public ?ObjectReference $standardTask = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->name, 'The task name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $planned = array_filter([
            'startDate' => $this->plannedStartDate?->value,
            'endDate' => $this->plannedEndDate?->value,
        ], static fn (?string $value): bool => $value !== null);

        $payload = array_filter([
            'id' => $this->id->value,
            'name' => $this->name,
            'project' => $this->project->toWriteArray(),
            'description' => $this->description,
            'parent' => $this->parent?->toWriteArray(),
            'taskStatus' => $this->status?->value,
            'planned' => $planned === [] ? null : $planned,
            'isMilestone' => $this->isMilestone,
            'isUtilized' => $this->isUtilized,
            'isBillable' => $this->isBillable,
            'item' => $this->item?->toWriteArray(),
            'timeType' => $this->timeType?->toWriteArray(),
            'class' => $this->class?->toWriteArray(),
            'standardTask' => $this->standardTask?->toWriteArray(),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
