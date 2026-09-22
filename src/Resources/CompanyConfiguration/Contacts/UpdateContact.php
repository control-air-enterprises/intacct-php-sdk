<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Contacts;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\MailingAddress;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use ControlAir\Intacct\ValueObjects\SensitiveString;

final readonly class UpdateContact
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function printAs(string $printAs): self
    {
        Assert::notBlank($printAs, 'The contact print-as name');

        return new self(['printAs' => $printAs]);
    }

    public static function status(RecordStatus $status): self
    {
        return new self(['status' => $status->value]);
    }

    public static function email1(?string $email): self
    {
        return new self(['email1' => $email]);
    }

    public static function mailingAddress(MailingAddress $address): self
    {
        return new self(['mailingAddress' => $address->toWriteArray()]);
    }

    public static function taxable(bool $taxable): self
    {
        return new self(['tax' => ['isTaxable' => $taxable]]);
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

    public function withPrintAs(string $printAs): self
    {
        Assert::notBlank($printAs, 'The contact print-as name');

        return $this->with('printAs', $printAs);
    }

    public function withPrefix(?string $prefix): self
    {
        return $this->with('prefix', $prefix);
    }

    public function withFirstName(?string $firstName): self
    {
        return $this->with('firstName', $firstName);
    }

    public function withMiddleName(?string $middleName): self
    {
        return $this->with('middleName', $middleName);
    }

    public function withLastName(?string $lastName): self
    {
        return $this->with('lastName', $lastName);
    }

    public function withCompanyName(?string $companyName): self
    {
        return $this->with('companyName', $companyName);
    }

    public function withEmail1(?string $email): self
    {
        return $this->with('email1', $email);
    }

    public function withEmail2(?string $email): self
    {
        return $this->with('email2', $email);
    }

    public function withPhone1(?string $phone): self
    {
        return $this->with('phone1', $phone);
    }

    public function withPhone2(?string $phone): self
    {
        return $this->with('phone2', $phone);
    }

    public function withMobile(?string $mobile): self
    {
        return $this->with('mobile', $mobile);
    }

    public function withFax(?string $fax): self
    {
        return $this->with('fax', $fax);
    }

    public function withUrl1(?string $url): self
    {
        return $this->with('URL1', $url);
    }

    public function withUrl2(?string $url): self
    {
        return $this->with('URL2', $url);
    }

    public function withStatus(RecordStatus $status): self
    {
        return $this->with('status', $status->value);
    }

    public function withShowInContactList(bool $show): self
    {
        return $this->with('showInContactList', $show);
    }

    /** Sends only the address fields that are set; omitted address fields are left unchanged. */
    public function withMailingAddress(MailingAddress $address): self
    {
        return $this->with('mailingAddress', $address->toWriteArray());
    }

    public function withTaxable(bool $taxable): self
    {
        return $this->withTax('isTaxable', $taxable);
    }

    public function withTaxId(?SensitiveString $taxId): self
    {
        return $this->withTax('taxId', $taxId?->reveal());
    }

    public function withTaxGroup(?ObjectReference $group): self
    {
        return $this->withTax('group', $group?->toWriteArray());
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

    private function with(string $field, mixed $value): self
    {
        return new self([...$this->changes, $field => $value]);
    }

    /** Tax fields share one nested `tax` object in the PATCH body. */
    private function withTax(string $field, mixed $value): self
    {
        $tax = $this->changes['tax'] ?? [];

        return $this->with('tax', [...(is_array($tax) ? $tax : []), $field => $value]);
    }
}
