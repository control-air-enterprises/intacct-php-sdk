<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Terms;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class Term
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?string $description,
        public ?RecordStatus $status,
        public ?TermDue $due,
        public ?TermDiscount $discount,
        public ?TermPenalty $penalty,
        public ?string $href,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');
        $due = ArrayReader::object($data['due'] ?? null);
        $discount = ArrayReader::object($data['discount'] ?? null);
        $penalty = ArrayReader::object($data['penalty'] ?? null);

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            description: ArrayReader::string($data, 'description'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            due: $due === null ? null : TermDue::fromArray($due),
            discount: $discount === null ? null : TermDiscount::fromArray($discount),
            penalty: $penalty === null ? null : TermPenalty::fromArray($penalty),
            href: ArrayReader::string($data, 'href'),
        );
    }
}
