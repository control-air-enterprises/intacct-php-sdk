<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Classes;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateClassDimension
{
    public function __construct(
        public ObjectId $id,
        public string $name,
        public ?string $description = null,
        public ?RecordStatus $status = null,
        public ?ObjectReference $parent = null,
    ) {
        Assert::notBlank($this->name, 'The class name');
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
        ], static fn (mixed $value): bool => $value !== null);
    }
}
