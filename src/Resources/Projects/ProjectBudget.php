<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;

final readonly class ProjectBudget
{
    public function __construct(
        public ?Decimal $billingAmount = null,
        public ?Decimal $budgetedDuration = null,
        public ?Decimal $budgetedCost = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            billingAmount: ArrayReader::decimal($data, 'billingAmount'),
            budgetedDuration: ArrayReader::decimal($data, 'budgetedDuration'),
            budgetedCost: ArrayReader::decimal($data, 'budgetedCost'),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'billingAmount' => $this->billingAmount?->value,
            'budgetedDuration' => $this->budgetedDuration?->value,
            'budgetedCost' => $this->budgetedCost?->value,
        ], static fn (?string $value): bool => $value !== null);
    }
}
