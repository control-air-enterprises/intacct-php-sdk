<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\Documents;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;

final readonly class UpdatePurchasingDocumentLine
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function unitQuantity(Decimal $unitQuantity): self
    {
        return new self(['unitQuantity' => $unitQuantity->value]);
    }

    public static function unitPrice(Decimal $unitPrice): self
    {
        return new self(['unitPrice' => $unitPrice->value]);
    }

    public static function lineDescription(?string $description): self
    {
        return new self(['lineDescription' => $description]);
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

    public function withUnitQuantity(Decimal $unitQuantity): self
    {
        return $this->with('unitQuantity', $unitQuantity->value);
    }

    public function withUnitPrice(Decimal $unitPrice): self
    {
        return $this->with('unitPrice', $unitPrice->value);
    }

    public function withLineDescription(?string $description): self
    {
        return $this->with('lineDescription', $description);
    }

    public function withMemo(?string $memo): self
    {
        return $this->with('memo', $memo);
    }

    public function withDimensions(Dimensions $dimensions): self
    {
        return $this->with('dimensions', $dimensions->toWriteArray());
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
