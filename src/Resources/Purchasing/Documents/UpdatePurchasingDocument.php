<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\Documents;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectKey;

/**
 * Header changes plus line operations. Sage applies line additions, updates and
 * removals from the same PATCH request.
 */
final readonly class UpdatePurchasingDocument
{
    /**
     * @param  array<string, mixed>  $changes
     * @param  list<array<string, mixed>>  $lines
     */
    private function __construct(
        private array $changes,
        private array $lines = [],
    ) {}

    public static function memo(?string $memo): self
    {
        return new self(['memo' => $memo]);
    }

    public static function dueDate(?LocalDate $dueDate): self
    {
        return new self(['dueDate' => $dueDate?->value]);
    }

    public static function addLine(CreatePurchasingDocumentLine $line): self
    {
        return new self([], [$line->toArray()]);
    }

    public static function updateLine(ObjectKey $key, UpdatePurchasingDocumentLine $line): self
    {
        return new self([], [['key' => $key->value, ...$line->toArray()]]);
    }

    public static function removeLine(ObjectKey $key): self
    {
        return new self([], [['ia::operation' => 'delete', 'key' => $key->value]]);
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

    public function withMemo(?string $memo): self
    {
        return $this->with('memo', $memo);
    }

    public function withNotes(?string $notes): self
    {
        return $this->with('notes', $notes);
    }

    public function withDueDate(?LocalDate $dueDate): self
    {
        return $this->with('dueDate', $dueDate?->value);
    }

    public function withReferenceNumber(?string $referenceNumber): self
    {
        return $this->with('referenceNumber', $referenceNumber);
    }

    public function withVendorDocumentNumber(?string $vendorDocumentNumber): self
    {
        return $this->with('vendorDocumentNumber', $vendorDocumentNumber);
    }

    public function withAddedLine(CreatePurchasingDocumentLine $line): self
    {
        return new self($this->changes, [...$this->lines, $line->toArray()]);
    }

    public function withUpdatedLine(ObjectKey $key, UpdatePurchasingDocumentLine $line): self
    {
        return new self($this->changes, [...$this->lines, ['key' => $key->value, ...$line->toArray()]]);
    }

    public function withRemovedLine(ObjectKey $key): self
    {
        return new self($this->changes, [...$this->lines, ['ia::operation' => 'delete', 'key' => $key->value]]);
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
        $payload = $this->lines === [] ? $this->changes : [...$this->changes, 'lines' => $this->lines];

        /** @var non-empty-array<string, mixed> $payload every named constructor sets a header field or a line */
        return $payload;
    }

    private function with(string $field, mixed $value): self
    {
        return new self([...$this->changes, $field => $value], $this->lines);
    }
}
