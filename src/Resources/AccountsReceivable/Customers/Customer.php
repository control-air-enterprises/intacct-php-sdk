<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsReceivable\Customers;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\SensitiveString;

final readonly class Customer
{
    /**
     * @param  ObjectReference|null  $defaultContact  The display contact Sage keeps on the customer record.
     */
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?CustomerStatus $status,
        public ?ObjectReference $customerType,
        public ?ObjectReference $parent,
        public ?ObjectReference $term,
        public ?ObjectReference $salesRepresentative,
        public ?ObjectReference $accountGroup,
        public ?ObjectReference $defaultRevenueGLAccount,
        public ?ObjectReference $defaultContact,
        public ?ObjectReference $primaryContact,
        public ?ObjectReference $billToContact,
        public ?ObjectReference $shipToContact,
        public ?string $currency,
        public ?SensitiveString $taxId,
        public ?Decimal $creditLimit,
        public ?Decimal $retainagePercentage,
        public ?Decimal $totalDue,
        public ?bool $onHold,
        public ?string $notes,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');
        $taxId = ArrayReader::string($data, 'taxId');
        $contacts = ArrayReader::object($data['contacts'] ?? null) ?? [];

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            name: ArrayReader::requiredString($data, 'name'),
            status: $status === null ? null : CustomerStatus::tryFrom($status),
            customerType: ArrayReader::reference($data, 'customerType'),
            parent: ArrayReader::reference($data, 'parent'),
            term: ArrayReader::reference($data, 'term'),
            salesRepresentative: ArrayReader::reference($data, 'salesRepresentative'),
            accountGroup: ArrayReader::reference($data, 'accountGroup'),
            defaultRevenueGLAccount: ArrayReader::reference($data, 'defaultRevenueGLAccount'),
            defaultContact: ArrayReader::reference($contacts, 'default'),
            primaryContact: ArrayReader::reference($contacts, 'primary'),
            billToContact: ArrayReader::reference($contacts, 'billTo'),
            shipToContact: ArrayReader::reference($contacts, 'shipTo'),
            currency: ArrayReader::string($data, 'currency'),
            taxId: $taxId === null ? null : new SensitiveString($taxId),
            creditLimit: ArrayReader::decimal($data, 'creditLimit'),
            retainagePercentage: ArrayReader::decimal($data, 'retainagePercentage'),
            totalDue: ArrayReader::decimal($data, 'totalDue'),
            onHold: ArrayReader::bool($data, 'isOnHold'),
            notes: ArrayReader::string($data, 'notes'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
