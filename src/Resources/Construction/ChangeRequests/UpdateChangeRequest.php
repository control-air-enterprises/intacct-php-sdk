<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ChangeRequests;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderExternalReference;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderInternalReference;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderSchedule;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

/**
 * Header changes plus line operations. Sage applies line additions, updates and removals
 * from the same PATCH request: a line without a key is added, a line with a key is
 * updated, and a key marked `ia::operation: delete` is removed. Only the fields named by
 * a call are sent; a null argument clears its field.
 */
final readonly class UpdateChangeRequest
{
    /**
     * @param  array<string, mixed>  $changes
     * @param  list<array<string, mixed>>  $lines
     */
    private function __construct(
        private array $changes,
        private array $lines = [],
    ) {}

    public static function description(?string $description): self
    {
        return new self(['description' => $description]);
    }

    public static function state(ChangeRequestState $state): self
    {
        return new self(['changeRequestState' => $state->value]);
    }

    public static function changeRequestStatus(?ObjectReference $status): self
    {
        return new self(['changeRequestStatus' => $status?->toWriteArray()]);
    }

    public static function addLine(CreateChangeRequestLine $line): self
    {
        return new self([], [$line->toArray()]);
    }

    public static function updateLine(ObjectKey $key, UpdateChangeRequestLine $line): self
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

    public function withDescription(?string $description): self
    {
        return $this->with('description', $description);
    }

    public function withState(ChangeRequestState $state): self
    {
        return $this->with('changeRequestState', $state->value);
    }

    public function withChangeRequestDate(?LocalDate $date): self
    {
        return $this->with('changeRequestDate', $date?->value);
    }

    public function withCostEffectiveDate(?LocalDate $date): self
    {
        return $this->with('costEffectiveDate', $date?->value);
    }

    public function withPriceEffectiveDate(?LocalDate $date): self
    {
        return $this->with('priceEffectiveDate', $date?->value);
    }

    public function withChangeRequestType(?ObjectReference $type): self
    {
        return $this->with('changeRequestType', $type?->toWriteArray());
    }

    public function withChangeRequestStatus(?ObjectReference $status): self
    {
        return $this->with('changeRequestStatus', $status?->toWriteArray());
    }

    public function withProject(ObjectReference $project): self
    {
        return $this->with('project', $project->toWriteArray());
    }

    public function withProjectContract(?ObjectReference $projectContract): self
    {
        return $this->with('projectContract', $projectContract?->toWriteArray());
    }

    public function withProjectContractLine(?ObjectReference $projectContractLine): self
    {
        return $this->with('projectContractLine', $projectContractLine?->toWriteArray());
    }

    public function withProjectContractLineSource(ChangeRequestContractLineSource $source): self
    {
        return $this->with('projectContractLineSource', $source->value);
    }

    public function withProjectChangeOrder(?ObjectReference $changeOrder): self
    {
        return $this->with('projectChangeOrder', $changeOrder?->toWriteArray());
    }

    public function withAttachment(?ObjectReference $attachment): self
    {
        return $this->with('attachment', $attachment?->toWriteArray());
    }

    public function withScope(?string $scope): self
    {
        return $this->with('scope', $scope);
    }

    public function withInclusions(?string $inclusions): self
    {
        return $this->with('inclusions', $inclusions);
    }

    public function withExclusions(?string $exclusions): self
    {
        return $this->with('exclusions', $exclusions);
    }

    public function withTerms(?string $terms): self
    {
        return $this->with('terms', $terms);
    }

    /** Replaces the whole schedule group: a null date clears that date. */
    public function withSchedule(ChangeOrderSchedule $schedule): self
    {
        return $this->with('schedule', $schedule->toWriteArray());
    }

    /** Replaces the whole internal reference group: a null field clears that field. */
    public function withInternalReference(ChangeOrderInternalReference $reference): self
    {
        return $this->with('internalReference', $reference->toWriteArray());
    }

    /** Replaces the whole external reference group: a null field clears that field. */
    public function withExternalReference(ChangeOrderExternalReference $reference): self
    {
        return $this->with('externalReference', $reference->toWriteArray());
    }

    public function withAddedLine(CreateChangeRequestLine $line): self
    {
        return new self($this->changes, [...$this->lines, $line->toArray()]);
    }

    public function withUpdatedLine(ObjectKey $key, UpdateChangeRequestLine $line): self
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

    /** @return array<string, mixed> Never empty: every named constructor sets a header field or a line. */
    public function toArray(): array
    {
        return $this->lines === [] ? $this->changes : [...$this->changes, 'changeRequestLines' => $this->lines];
    }

    private function with(string $field, mixed $value): self
    {
        return new self([...$this->changes, $field => $value], $this->lines);
    }
}
