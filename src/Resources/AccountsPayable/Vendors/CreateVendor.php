<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Vendors;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\SensitiveString;

final readonly class CreateVendor
{
    public function __construct(
        public ObjectId $id,
        public string $name,
        public ?VendorStatus $status = null,
        public ?ObjectReference $vendorType = null,
        public ?ObjectReference $parent = null,
        public ?ObjectReference $term = null,
        public ?string $currency = null,
        public ?string $vendorAccountNumber = null,
        public ?SensitiveString $taxId = null,
        public ?Decimal $creditLimit = null,
        public ?bool $onHold = null,
        public ?string $notes = null,
    ) {
        Assert::notBlank($this->name, 'The vendor name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id->value,
            'name' => $this->name,
            'status' => $this->status?->value,
            'vendorType' => $this->vendorType?->toWriteArray(),
            'parent' => $this->parent?->toWriteArray(),
            'term' => $this->term?->toWriteArray(),
            'currency' => $this->currency,
            'vendorAccountNumber' => $this->vendorAccountNumber,
            'taxId' => $this->taxId?->reveal(),
            'creditLimit' => $this->creditLimit?->value,
            'isOnHold' => $this->onHold,
            'notes' => $this->notes,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
