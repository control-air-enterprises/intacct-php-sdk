<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time\TimeTypes;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

/**
 * A time type has no separate name: its ID, such as "Regular", is the name shown on timesheets.
 */
final readonly class TimeType
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?RecordStatus $status,
        public ?ObjectReference $earningType,
        public ?ObjectReference $glAccount,
        public ?ObjectReference $offsetGLAccount,
        public ?ObjectReference $entity,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            earningType: ArrayReader::reference($data, 'earningType'),
            glAccount: ArrayReader::reference($data, 'glAccount'),
            offsetGLAccount: ArrayReader::reference($data, 'offsetGLAccount'),
            entity: ArrayReader::reference($data, 'entity'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
