<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects\ProjectResources;

use ControlAir\Intacct\ValueObjects\LocalDate;

final readonly class UpdateProjectResource
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function description(?string $description): self
    {
        return new self(['description' => $description]);
    }

    public function withStartDate(?LocalDate $startDate): self
    {
        return $this->with('startDate', $startDate?->value);
    }

    public function withPricing(?ProjectResourcePricing $pricing): self
    {
        return $this->with('pricing', $pricing?->toArray());
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
