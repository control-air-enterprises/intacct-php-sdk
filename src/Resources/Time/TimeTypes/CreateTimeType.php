<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time\TimeTypes;

use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CreateTimeType
{
    /**
     * @param  ObjectId  $id  The time type name, such as "Overtime"; it cannot be changed later.
     * @param  ObjectReference|null  $glAccount  The labor cost account debited when timesheets post.
     * @param  ObjectReference|null  $offsetGLAccount  The account credited when timesheets post.
     */
    public function __construct(
        public ObjectId $id,
        public ?RecordStatus $status = null,
        public ?ObjectReference $glAccount = null,
        public ?ObjectReference $offsetGLAccount = null,
        public ?ObjectReference $earningType = null,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'id' => $this->id->value,
            'status' => $this->status?->value,
            'glAccount' => $this->glAccount?->toWriteArray(),
            'offsetGLAccount' => $this->offsetGLAccount?->toWriteArray(),
            'earningType' => $this->earningType?->toWriteArray(),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
