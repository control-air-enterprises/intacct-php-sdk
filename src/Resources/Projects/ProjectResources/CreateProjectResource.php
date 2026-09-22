<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects\ProjectResources;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class CreateProjectResource
{
    public function __construct(
        public ObjectReference $project,
        public ?ObjectReference $employee = null,
        public ?ObjectReference $item = null,
        public ?string $description = null,
        public ?LocalDate $startDate = null,
        public ?ProjectResourcePricing $pricing = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        if ($this->employee === null && $this->item === null) {
            throw new InvalidArgument('A project resource requires an employee or an item.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'project' => $this->project->toWriteArray(),
            'employee' => $this->employee?->toWriteArray(),
            'item' => $this->item?->toWriteArray(),
            'description' => $this->description,
            'startDate' => $this->startDate?->value,
            'pricing' => $this->pricing?->toArray(),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
