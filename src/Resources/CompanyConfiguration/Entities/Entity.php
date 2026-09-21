<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Entities;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class Entity
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?ObjectReference $manager,
        public ?LocalDate $startDate,
        public ?LocalDate $endDate,
        public ?RecordStatus $status,
        public ?string $federalId,
        public ?string $firstFiscalMonth,
        public ?ObjectReference $baseCurrency,
        public ?string $weekStart,
        public ?bool $isRoot,
        public ?string $taxId,
        public ?string $operatingCountry,
        public ?string $accountingType,
        public ?bool $isLimitedEntity,
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
            manager: ArrayReader::reference($data, 'manager'),
            startDate: ArrayReader::date($data, 'startDate'),
            endDate: ArrayReader::date($data, 'endDate'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            federalId: ArrayReader::string($data, 'federalId'),
            firstFiscalMonth: ArrayReader::string($data, 'firstFiscalMonth'),
            baseCurrency: ArrayReader::reference($data, 'baseCurrency'),
            weekStart: ArrayReader::string($data, 'weekStart'),
            isRoot: ArrayReader::bool($data, 'isRoot'),
            taxId: ArrayReader::string($data, 'taxId'),
            operatingCountry: ArrayReader::string($data, 'operatingCountry'),
            accountingType: ArrayReader::string($data, 'accountingType'),
            isLimitedEntity: ArrayReader::bool($data, 'isLimitedEntity'),
            href: ArrayReader::string($data, 'href'),
        );
    }
}
