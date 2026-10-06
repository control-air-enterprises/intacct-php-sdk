<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectChangeOrders;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

/**
 * Only the fields named by a call are sent; a null argument clears its field.
 */
final readonly class UpdateProjectChangeOrder
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function description(?string $description): self
    {
        return new self(['description' => $description]);
    }

    public static function state(ProjectChangeOrderState $state): self
    {
        return new self(['state' => $state->value]);
    }

    public static function changeRequestStatus(?ObjectReference $status): self
    {
        return new self(['changeRequestStatus' => $status?->toWriteArray()]);
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

    public function withState(ProjectChangeOrderState $state): self
    {
        return $this->with('state', $state->value);
    }

    public function withStatus(RecordStatus $status): self
    {
        return $this->with('status', $status->value);
    }

    public function withProjectChangeOrderDate(?LocalDate $date): self
    {
        return $this->with('projectChangeOrderDate', $date?->value);
    }

    public function withPriceEffectiveDate(?LocalDate $date): self
    {
        return $this->with('priceEffectiveDate', $date?->value);
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

    public function withChangeRequestStatus(?ObjectReference $status): self
    {
        return $this->with('changeRequestStatus', $status?->toWriteArray());
    }

    public function withItem(?ObjectReference $item): self
    {
        return $this->with('item', $item?->toWriteArray());
    }

    public function withSendToContact(?ObjectReference $contact): self
    {
        return $this->with('sendToContact', $contact?->toWriteArray());
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
