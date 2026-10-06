<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time\Timesheets;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class UpdateTimesheetLine
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function quantity(Decimal $quantity): self
    {
        return new self(['quantity' => $quantity->value]);
    }

    public static function entryDate(LocalDate $entryDate): self
    {
        return new self(['entryDate' => $entryDate->value]);
    }

    public static function dimensions(Dimensions $dimensions): self
    {
        return new self(['dimensions' => $dimensions->toWriteArray()]);
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

    public function withQuantity(Decimal $quantity): self
    {
        return $this->with('quantity', $quantity->value);
    }

    public function withEntryDate(LocalDate $entryDate): self
    {
        return $this->with('entryDate', $entryDate->value);
    }

    public function withTimeType(?ObjectReference $timeType): self
    {
        return $this->with('timeType', $timeType?->toWriteArray());
    }

    public function withDimensions(Dimensions $dimensions): self
    {
        return $this->with('dimensions', $dimensions->toWriteArray());
    }

    public function withBillable(bool $billable): self
    {
        return $this->with('isBillable', $billable);
    }

    public function withDescription(?string $description): self
    {
        return $this->with('description', $description);
    }

    public function withNotes(?string $notes): self
    {
        return $this->with('notes', $notes);
    }

    public function withLaborClass(?ObjectReference $laborClass): self
    {
        return $this->with('laborClass', $laborClass?->toWriteArray());
    }

    public function withLaborShift(?ObjectReference $laborShift): self
    {
        return $this->with('laborShift', $laborShift?->toWriteArray());
    }

    public function withLaborUnion(?ObjectReference $laborUnion): self
    {
        return $this->with('laborUnion', $laborUnion?->toWriteArray());
    }

    public function withEmployeePosition(?ObjectReference $employeePosition): self
    {
        return $this->with('employeePosition', $employeePosition?->toWriteArray());
    }

    /** Sets only the external payroll values that are given; the others are left unchanged. */
    public function withExternalPayroll(TimesheetExternalPayroll $externalPayroll): self
    {
        return $this->with('externalPayroll', $externalPayroll->toWriteArray());
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
