<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class UpdateProject
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function name(string $name): self
    {
        Assert::notBlank($name, 'The project name');

        return new self(['name' => $name]);
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

    public function withDescription(?string $description): self
    {
        return $this->with('description', $description);
    }

    public function withStatus(RecordStatus $status): self
    {
        return $this->with('status', $status->value);
    }

    public function withDates(?LocalDate $startDate, ?LocalDate $endDate): self
    {
        return $this
            ->with('startDate', $startDate?->value)
            ->with('endDate', $endDate?->value);
    }

    public function withBudget(?ProjectBudget $budget): self
    {
        return $this->with('budget', $budget?->toArray());
    }

    public function withManager(?ObjectReference $manager): self
    {
        return $this->with('manager', $manager?->toWriteArray());
    }

    public function withDepartment(?ObjectReference $department): self
    {
        return $this->with('department', $department?->toWriteArray());
    }

    public function withLocation(?ObjectReference $location): self
    {
        return $this->with('location', $location?->toWriteArray());
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
