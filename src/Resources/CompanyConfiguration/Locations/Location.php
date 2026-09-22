<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Locations;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class Location
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?string $description,
        public ?string $reportTitle,
        public ?string $printAs,
        public ?RecordStatus $status,
        public ?LocalDate $startDate,
        public ?LocalDate $endDate,
        public ?ObjectReference $parent,
        public ?ObjectReference $manager,
        public ?ObjectReference $entity,
        public ?string $baseCurrency,
        public ?string $taxId,
        public ?string $businessId,
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
            name: ArrayReader::requiredString($data, 'name'),
            description: ArrayReader::string($data, 'description'),
            reportTitle: ArrayReader::string($data, 'reportTitle'),
            printAs: ArrayReader::string($data, 'printAs'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            startDate: ArrayReader::date($data, 'startDate'),
            endDate: ArrayReader::date($data, 'endDate'),
            parent: ArrayReader::reference($data, 'parent'),
            manager: ArrayReader::reference($data, 'manager'),
            entity: ArrayReader::reference($data, 'entity'),
            baseCurrency: ArrayReader::string($data, 'baseCurrency'),
            taxId: ArrayReader::string($data, 'taxId'),
            businessId: ArrayReader::string($data, 'businessId'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
