<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Locations;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateLocation
{
    public function __construct(
        public ObjectId $id,
        public string $name,
        public ?string $description = null,
        public ?RecordStatus $status = null,
        public ?ObjectReference $parent = null,
        public ?ObjectReference $manager = null,
        public ?LocalDate $startDate = null,
        public ?LocalDate $endDate = null,
        public ?string $reportTitle = null,
        public ?string $printAs = null,
    ) {
        Assert::notBlank($this->name, 'The location name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id->value,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value,
            'parent' => $this->parent?->toWriteArray(),
            'manager' => $this->manager?->toWriteArray(),
            'startDate' => $this->startDate?->value,
            'endDate' => $this->endDate?->value,
            'reportTitle' => $this->reportTitle,
            'printAs' => $this->printAs,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
