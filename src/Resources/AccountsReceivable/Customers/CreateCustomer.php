<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsReceivable\Customers;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\SensitiveString;

final readonly class CreateCustomer
{
    /**
     * @param  ObjectReference|null  $primaryContact  An existing contact; the bill-to and ship-to contacts work the same way.
     */
    public function __construct(
        public ObjectId $id,
        public string $name,
        public ?CustomerStatus $status = null,
        public ?ObjectReference $customerType = null,
        public ?ObjectReference $parent = null,
        public ?ObjectReference $term = null,
        public ?ObjectReference $salesRepresentative = null,
        public ?ObjectReference $accountGroup = null,
        public ?ObjectReference $defaultRevenueGLAccount = null,
        public ?ObjectReference $primaryContact = null,
        public ?ObjectReference $billToContact = null,
        public ?ObjectReference $shipToContact = null,
        public ?string $currency = null,
        public ?SensitiveString $taxId = null,
        public ?Decimal $creditLimit = null,
        public ?Decimal $retainagePercentage = null,
        public ?bool $onHold = null,
        public ?string $notes = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->name, 'The customer name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $contacts = array_filter([
            'primary' => $this->primaryContact?->toWriteArray(),
            'billTo' => $this->billToContact?->toWriteArray(),
            'shipTo' => $this->shipToContact?->toWriteArray(),
        ], static fn (?array $value): bool => $value !== null);

        $payload = array_filter([
            'id' => $this->id->value,
            'name' => $this->name,
            'status' => $this->status?->value,
            'customerType' => $this->customerType?->toWriteArray(),
            'parent' => $this->parent?->toWriteArray(),
            'term' => $this->term?->toWriteArray(),
            'salesRepresentative' => $this->salesRepresentative?->toWriteArray(),
            'accountGroup' => $this->accountGroup?->toWriteArray(),
            'defaultRevenueGLAccount' => $this->defaultRevenueGLAccount?->toWriteArray(),
            'contacts' => $contacts === [] ? null : $contacts,
            'currency' => $this->currency,
            'taxId' => $this->taxId?->reveal(),
            'creditLimit' => $this->creditLimit?->value,
            'retainagePercentage' => $this->retainagePercentage?->value,
            'isOnHold' => $this->onHold,
            'notes' => $this->notes,
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
