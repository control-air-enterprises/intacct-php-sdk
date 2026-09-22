<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Terms;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\RecordStatus;

/**
 * A PATCH change-set. Only the fields set here are sent. A nested group sends only the
 * values it carries, so `UpdateTerm::penalty(new TermPenalty(amount: ...))` leaves the
 * other penalty settings untouched.
 */
final readonly class UpdateTerm
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function description(string $description): self
    {
        Assert::notBlank($description, 'The term description');

        return new self(['description' => $description]);
    }

    public static function status(RecordStatus $status): self
    {
        return new self(['status' => $status->value]);
    }

    public static function due(TermDue $due): self
    {
        return new self(['due' => $due->toArray()]);
    }

    public static function discount(TermDiscount $discount): self
    {
        return new self(['discount' => $discount->toArray()]);
    }

    public static function penalty(TermPenalty $penalty): self
    {
        return new self(['penalty' => $penalty->toArray()]);
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

    public function withDescription(string $description): self
    {
        Assert::notBlank($description, 'The term description');

        return $this->with('description', $description);
    }

    public function withStatus(RecordStatus $status): self
    {
        return $this->with('status', $status->value);
    }

    public function withDue(TermDue $due): self
    {
        return $this->with('due', $due->toArray());
    }

    public function withDiscount(TermDiscount $discount): self
    {
        return $this->with('discount', $discount->toArray());
    }

    public function withPenalty(TermPenalty $penalty): self
    {
        return $this->with('penalty', $penalty->toArray());
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
