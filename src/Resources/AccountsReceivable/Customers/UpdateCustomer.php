<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsReceivable\Customers;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\SensitiveString;

final readonly class UpdateCustomer
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function name(string $name): self
    {
        Assert::notBlank($name, 'The customer name');

        return new self(['name' => $name]);
    }

    public static function status(CustomerStatus $status): self
    {
        return new self(['status' => $status->value]);
    }

    /** Starts a change set with one custom field, named with or without the `nsp::` prefix; null clears it. */
    public static function customField(string $name, mixed $value): self
    {
        return self::customFields((new CustomFields)->with($name, $value));
    }

    /** Starts a change set with custom fields; a null value clears its field. */
    public static function customFields(CustomFields $fields): self
    {
        $changes = $fields->toWriteArray();

        if ($changes === []) {
            throw new InvalidArgument('At least one custom field is required.');
        }

        return new self($changes);
    }

    public function withName(string $name): self
    {
        Assert::notBlank($name, 'The customer name');

        return $this->with('name', $name);
    }

    public function withStatus(CustomerStatus $status): self
    {
        return $this->with('status', $status->value);
    }

    public function withCustomerType(?ObjectReference $customerType): self
    {
        return $this->with('customerType', $customerType?->toWriteArray());
    }

    public function withParent(?ObjectReference $parent): self
    {
        return $this->with('parent', $parent?->toWriteArray());
    }

    public function withTerm(?ObjectReference $term): self
    {
        return $this->with('term', $term?->toWriteArray());
    }

    public function withSalesRepresentative(?ObjectReference $salesRepresentative): self
    {
        return $this->with('salesRepresentative', $salesRepresentative?->toWriteArray());
    }

    public function withAccountGroup(?ObjectReference $accountGroup): self
    {
        return $this->with('accountGroup', $accountGroup?->toWriteArray());
    }

    public function withDefaultRevenueGLAccount(?ObjectReference $account): self
    {
        return $this->with('defaultRevenueGLAccount', $account?->toWriteArray());
    }

    public function withPrimaryContact(?ObjectReference $contact): self
    {
        return $this->withContact('primary', $contact);
    }

    public function withBillToContact(?ObjectReference $contact): self
    {
        return $this->withContact('billTo', $contact);
    }

    public function withShipToContact(?ObjectReference $contact): self
    {
        return $this->withContact('shipTo', $contact);
    }

    public function withTaxId(?SensitiveString $taxId): self
    {
        return $this->with('taxId', $taxId?->reveal());
    }

    public function withCreditLimit(?Decimal $creditLimit): self
    {
        return $this->with('creditLimit', $creditLimit?->value);
    }

    public function withRetainagePercentage(?Decimal $retainagePercentage): self
    {
        return $this->with('retainagePercentage', $retainagePercentage?->value);
    }

    public function withOnHold(bool $onHold): self
    {
        return $this->with('isOnHold', $onHold);
    }

    public function withNotes(?string $notes): self
    {
        return $this->with('notes', $notes);
    }

    /** Sets a custom field, named with or without the `nsp::` prefix; null clears it. */
    public function withCustomField(string $name, mixed $value): self
    {
        return $this->withCustomFields((new CustomFields)->with($name, $value));
    }

    /** Sets custom fields; a null value clears its field. */
    public function withCustomFields(CustomFields $fields): self
    {
        $update = $this;

        foreach ($fields->toWriteArray() as $field => $value) {
            $update = $update->with($field, $value);
        }

        return $update;
    }

    /** @return non-empty-array<string, mixed> */
    public function toArray(): array
    {
        return $this->changes;
    }

    private function withContact(string $role, ?ObjectReference $contact): self
    {
        $contacts = $this->changes['contacts'] ?? [];

        return $this->with('contacts', [...(is_array($contacts) ? $contacts : []), $role => $contact?->toWriteArray()]);
    }

    private function with(string $field, mixed $value): self
    {
        return new self([...$this->changes, $field => $value]);
    }
}
