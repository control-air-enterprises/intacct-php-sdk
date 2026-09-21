<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects\Tasks;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class UpdateTask
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function name(string $name): self
    {
        Assert::notBlank($name, 'The task name');

        return new self(['name' => $name]);
    }

    public function withDescription(?string $description): self
    {
        return $this->with('description', $description);
    }

    public function withStatus(TaskStatus $status): self
    {
        return $this->with('taskStatus', $status->value);
    }

    public function withPlannedDates(?LocalDate $startDate, ?LocalDate $endDate): self
    {
        return $this->with('planned', [
            'startDate' => $startDate?->value,
            'endDate' => $endDate?->value,
        ]);
    }

    public function withParent(?ObjectReference $parent): self
    {
        return $this->with('parent', $parent?->toWriteArray());
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
