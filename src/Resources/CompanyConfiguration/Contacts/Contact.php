<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Contacts;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\MailingAddress;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use ControlAir\Intacct\ValueObjects\SensitiveString;

final readonly class Contact
{
    /**
     * @param  ObjectReference|null  $taxGroup  A company-config/contact-tax-group reference.
     * @param  SensitiveString|null  $taxId  Null on query results; read the contact to retrieve it.
     */
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $printAs,
        public ?string $prefix,
        public ?string $firstName,
        public ?string $middleName,
        public ?string $lastName,
        public ?string $companyName,
        public ?string $email1,
        public ?string $email2,
        public ?string $phone1,
        public ?string $phone2,
        public ?string $mobile,
        public ?string $fax,
        public ?string $url1,
        public ?string $url2,
        public ?RecordStatus $status,
        public ?bool $showInContactList,
        public ?MailingAddress $mailingAddress,
        public ?bool $taxable,
        public ?ObjectReference $taxGroup,
        public ?SensitiveString $taxId,
        public ?ObjectReference $entity,
        public ?string $href,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');
        $address = ArrayReader::object($data['mailingAddress'] ?? null);
        $tax = ArrayReader::object($data['tax'] ?? null) ?? [];
        $taxId = ArrayReader::string($tax, 'taxId');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            printAs: ArrayReader::requiredString($data, 'printAs'),
            prefix: ArrayReader::string($data, 'prefix'),
            firstName: ArrayReader::string($data, 'firstName'),
            middleName: ArrayReader::string($data, 'middleName'),
            lastName: ArrayReader::string($data, 'lastName'),
            companyName: ArrayReader::string($data, 'companyName'),
            email1: ArrayReader::string($data, 'email1'),
            email2: ArrayReader::string($data, 'email2'),
            phone1: ArrayReader::string($data, 'phone1'),
            phone2: ArrayReader::string($data, 'phone2'),
            mobile: ArrayReader::string($data, 'mobile'),
            fax: ArrayReader::string($data, 'fax'),
            url1: ArrayReader::string($data, 'URL1'),
            url2: ArrayReader::string($data, 'URL2'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            showInContactList: ArrayReader::bool($data, 'showInContactList'),
            mailingAddress: $address === null ? null : MailingAddress::fromArray($address),
            taxable: ArrayReader::bool($tax, 'isTaxable'),
            taxGroup: ArrayReader::reference($tax, 'group'),
            taxId: $taxId === null ? null : new SensitiveString($taxId),
            entity: ArrayReader::reference($data, 'entity'),
            href: ArrayReader::string($data, 'href'),
        );
    }
}
