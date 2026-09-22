<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Vendors;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class UpdateVendor
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function name(string $name): self
    {
        Assert::notBlank($name, 'The vendor name');

        return new self(['name' => $name]);
    }

    public static function status(VendorStatus $status): self
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

    public function withStatus(VendorStatus $status): self
    {
        return $this->with('status', $status->value);
    }

    public function withVendorType(?ObjectReference $vendorType): self
    {
        return $this->with('vendorType', $vendorType?->toWriteArray());
    }

    public function withTerm(?ObjectReference $term): self
    {
        return $this->with('term', $term?->toWriteArray());
    }

    public function withCreditLimit(?Decimal $creditLimit): self
    {
        return $this->with('creditLimit', $creditLimit?->value);
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

    private function with(string $field, mixed $value): self
    {
        return new self([...$this->changes, $field => $value]);
    }
}
