<?php

declare(strict_types=1);

namespace ControlAir\Intacct\ValueObjects;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\ArrayReader;

/**
 * The Sage `mailingAddress` object shared by contacts, vendors, warehouses and documents.
 */
final readonly class MailingAddress
{
    /**
     * @param  string|null  $country  The country name, such as "United States".
     * @param  string|null  $isoCountryCode  The ISO 3166-1 alpha-2 code; Sage returns it in lowercase, such as "us".
     */
    public function __construct(
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $addressLine3 = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $postCode = null,
        public ?string $country = null,
        public ?string $isoCountryCode = null,
    ) {
        if ($this->toWriteArray() === []) {
            throw new InvalidArgument('A mailing address requires at least one field.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $fields = [
            'addressLine1' => ArrayReader::string($data, 'addressLine1'),
            'addressLine2' => ArrayReader::string($data, 'addressLine2'),
            'addressLine3' => ArrayReader::string($data, 'addressLine3'),
            'city' => ArrayReader::string($data, 'city'),
            'state' => ArrayReader::string($data, 'state'),
            'postCode' => ArrayReader::string($data, 'postCode'),
            'country' => ArrayReader::string($data, 'country'),
            'isoCountryCode' => ArrayReader::string($data, 'isoCountryCode'),
        ];

        if (array_filter($fields, static fn (?string $value): bool => $value !== null) === []) {
            return null;
        }

        return new self(...$fields);
    }

    /**
     * Only the fields that are set, so a PATCH leaves the omitted address fields unchanged.
     *
     * @return array<string, string>
     */
    public function toWriteArray(): array
    {
        return array_filter([
            'addressLine1' => $this->addressLine1,
            'addressLine2' => $this->addressLine2,
            'addressLine3' => $this->addressLine3,
            'city' => $this->city,
            'state' => $this->state,
            'postCode' => $this->postCode,
            'country' => $this->country,
            'isoCountryCode' => $this->isoCountryCode,
        ], static fn (?string $value): bool => $value !== null);
    }
}
