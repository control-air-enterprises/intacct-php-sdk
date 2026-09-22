<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\Documents;

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
