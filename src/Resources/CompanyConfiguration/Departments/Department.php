<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Departments;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class Department
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?string $reportTitle,
        public ?RecordStatus $status,
        public ?ObjectReference $parent,
        public ?ObjectReference $supervisor,
        public ?string $href,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            name: ArrayReader::requiredString($data, 'name'),
            reportTitle: ArrayReader::string($data, 'reportTitle'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            parent: ArrayReader::reference($data, 'parent'),
            supervisor: ArrayReader::reference($data, 'supervisor'),
            href: ArrayReader::string($data, 'href'),
        );
    }
}
