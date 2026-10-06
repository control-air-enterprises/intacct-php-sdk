<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\EmployeePositions;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateEmployeePosition
{
    /** @param  ObjectId  $id  Set once on create; Sage does not allow changing it afterwards. */
    public function __construct(
        public ObjectId $id,
        public string $name,
        public ?string $description = null,
        public ?RecordStatus $status = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->name, 'The employee position name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'id' => $this->id->value,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value,
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
