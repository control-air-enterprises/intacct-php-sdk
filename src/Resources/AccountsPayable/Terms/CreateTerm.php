<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Terms;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateTerm
{
    public function __construct(
        public ObjectId $id,
        public string $description,
        public ?RecordStatus $status = null,
        public ?TermDue $due = null,
        public ?TermDiscount $discount = null,
        public ?TermPenalty $penalty = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->description, 'The term description');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'id' => $this->id->value,
            'description' => $this->description,
            'status' => $this->status?->value,
            'due' => $this->due?->toArray(),
            'discount' => $this->discount?->toArray(),
            'penalty' => $this->penalty?->toArray(),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
