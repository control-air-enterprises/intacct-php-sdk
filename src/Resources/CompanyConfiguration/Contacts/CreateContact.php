<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Contacts;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\MailingAddress;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use ControlAir\Intacct\ValueObjects\SensitiveString;

final readonly class CreateContact
{
    /**
     * @param  string  $printAs  The display name Sage prints on documents, such as "Andy Moore".
     * @param  ObjectReference|null  $taxGroup  A company-config/contact-tax-group reference.
     */
    public function __construct(
        public ObjectId $id,
        public string $printAs,
        public ?string $prefix = null,
        public ?string $firstName = null,
        public ?string $middleName = null,
        public ?string $lastName = null,
        public ?string $companyName = null,
        public ?string $email1 = null,
        public ?string $email2 = null,
        public ?string $phone1 = null,
        public ?string $phone2 = null,
        public ?string $mobile = null,
        public ?string $fax = null,
        public ?string $url1 = null,
        public ?string $url2 = null,
        public ?RecordStatus $status = null,
        public ?bool $showInContactList = null,
        public ?MailingAddress $mailingAddress = null,
        public ?bool $taxable = null,
        public ?ObjectReference $taxGroup = null,
        public ?SensitiveString $taxId = null,
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->printAs, 'The contact print-as name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $tax = array_filter([
            'isTaxable' => $this->taxable,
            'taxId' => $this->taxId?->reveal(),
            'group' => $this->taxGroup?->toWriteArray(),
        ], static fn (mixed $value): bool => $value !== null);

        $payload = array_filter([
            'id' => $this->id->value,
            'printAs' => $this->printAs,
            'prefix' => $this->prefix,
            'firstName' => $this->firstName,
            'middleName' => $this->middleName,
            'lastName' => $this->lastName,
            'companyName' => $this->companyName,
            'email1' => $this->email1,
            'email2' => $this->email2,
            'phone1' => $this->phone1,
            'phone2' => $this->phone2,
            'mobile' => $this->mobile,
            'fax' => $this->fax,
            'URL1' => $this->url1,
            'URL2' => $this->url2,
            'status' => $this->status?->value,
            'showInContactList' => $this->showInContactList,
            'mailingAddress' => $this->mailingAddress?->toWriteArray(),
            'tax' => $tax === [] ? null : $tax,
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
