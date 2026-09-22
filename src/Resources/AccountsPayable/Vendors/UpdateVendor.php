<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Vendors;

use ControlAir\Intacct\Support\Assert;
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
