<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\CostTypes;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateCostType
{
    public function __construct(
        public ObjectReference $project,
        public ObjectReference $task,
        public string $name,
        public ?ObjectId $id = null,
        public ?string $description = null,
        public ?RecordStatus $status = null,
        public ?string $costUnitDescription = null,
        public ?ObjectReference $accumulationType = null,
        public ?ObjectReference $glAccount = null,
        public ?ObjectReference $item = null,
        public ?ObjectReference $standardCostType = null,
        public ?LocalDate $plannedStartDate = null,
        public ?LocalDate $plannedEndDate = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->name, 'The cost type name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $planned = array_filter([
            'startDate' => $this->plannedStartDate?->value,
            'endDate' => $this->plannedEndDate?->value,
        ], static fn (?string $value): bool => $value !== null);

        $payload = array_filter([
            'project' => $this->project->toWriteArray(),
            'task' => $this->task->toWriteArray(),
            'id' => $this->id?->value,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value,
            'costUnitDescription' => $this->costUnitDescription,
            'accumulationType' => $this->accumulationType?->toWriteArray(),
            'glAccount' => $this->glAccount?->toWriteArray(),
            'item' => $this->item?->toWriteArray(),
            'standardCostType' => $this->standardCostType?->toWriteArray(),
            'planned' => $planned === [] ? null : $planned,
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
