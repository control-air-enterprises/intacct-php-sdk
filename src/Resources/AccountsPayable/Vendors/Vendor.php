<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Vendors;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\SensitiveString;

final readonly class Vendor
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?VendorStatus $status,
        public ?ObjectReference $vendorType,
        public ?ObjectReference $parent,
        public ?ObjectReference $term,
        public ?string $currency,
        public ?string $vendorAccountNumber,
        public ?SensitiveString $taxId,
        public ?Decimal $creditLimit,
        public ?Decimal $totalDue,
        public ?bool $onHold,
        public ?string $notes,
        public ?string $href,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');
        $taxId = ArrayReader::string($data, 'taxId');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            name: ArrayReader::requiredString($data, 'name'),
            status: $status === null ? null : VendorStatus::tryFrom($status),
            vendorType: ArrayReader::reference($data, 'vendorType'),
            parent: ArrayReader::reference($data, 'parent'),
            term: ArrayReader::reference($data, 'term'),
            currency: ArrayReader::string($data, 'currency'),
            vendorAccountNumber: ArrayReader::string($data, 'vendorAccountNumber'),
            taxId: $taxId === null ? null : new SensitiveString($taxId),
            creditLimit: ArrayReader::decimal($data, 'creditLimit'),
            totalDue: ArrayReader::decimal($data, 'totalDue'),
            onHold: ArrayReader::bool($data, 'isOnHold'),
            notes: ArrayReader::string($data, 'notes'),
            href: ArrayReader::string($data, 'href'),
        );
    }
}
