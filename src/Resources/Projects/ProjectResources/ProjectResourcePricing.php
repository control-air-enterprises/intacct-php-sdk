<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects\ProjectResources;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;

final readonly class ProjectResourcePricing
{
    public function __construct(
        public ?string $laborPricingMethod = null,
        public ?Decimal $laborRate = null,
        public ?string $expensePricingMethod = null,
        public ?Decimal $expenseRate = null,
        public ?string $apPurchasingPricingMethod = null,
        public ?Decimal $apPurchasingRate = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            laborPricingMethod: ArrayReader::string($data, 'laborPricingMethod'),
            laborRate: ArrayReader::decimal($data, 'laborRate'),
            expensePricingMethod: ArrayReader::string($data, 'expensePricingMethod'),
            expenseRate: ArrayReader::decimal($data, 'expenseRate'),
            apPurchasingPricingMethod: ArrayReader::string($data, 'apPurchasingPricingMethod'),
            apPurchasingRate: ArrayReader::decimal($data, 'apPurchasingRate'),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'laborPricingMethod' => $this->laborPricingMethod,
            'laborRate' => $this->laborRate?->value,
            'expensePricingMethod' => $this->expensePricingMethod,
            'expenseRate' => $this->expenseRate?->value,
            'apPurchasingPricingMethod' => $this->apPurchasingPricingMethod,
            'apPurchasingRate' => $this->apPurchasingRate?->value,
        ], static fn (?string $value): bool => $value !== null);
    }
}
