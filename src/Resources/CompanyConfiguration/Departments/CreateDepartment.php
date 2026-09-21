<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Departments;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateDepartment
{
    public function __construct(
        public ObjectId $id,
        public string $name,
        public ?string $reportTitle = null,
        public ?RecordStatus $status = null,
        public ?ObjectReference $parent = null,
        public ?ObjectReference $supervisor = null,
    ) {
        Assert::notBlank($this->name, 'The department name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id->value,
            'name' => $this->name,
            'reportTitle' => $this->reportTitle,
            'status' => $this->status?->value,
            'parent' => $this->parent?->toWriteArray(),
            'supervisor' => $this->supervisor?->toWriteArray(),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
