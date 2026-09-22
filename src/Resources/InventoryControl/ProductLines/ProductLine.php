<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\ProductLines;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class ProductLine
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?string $description,
        public ?RecordStatus $status,
        public ?ObjectReference $parent,
        public ?string $href,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            description: ArrayReader::string($data, 'description'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            parent: ArrayReader::reference($data, 'parent'),
            href: ArrayReader::string($data, 'href'),
        );
    }
}
