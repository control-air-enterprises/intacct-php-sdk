<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContractLines;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\ProjectContractSchedule;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

/**
 * Line changes plus entry operations. Sage applies entry additions and removals from the
 * same PATCH request; to change an entry, remove it and add a replacement.
 */
final readonly class UpdateProjectContractLine
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function name(string $name): self
    {
        Assert::notBlank($name, 'The project contract line name');

        return new self(['name' => $name]);
    }

    public static function description(?string $description): self
    {
        return new self(['description' => $description]);
    }

    public static function status(RecordStatus $status): self
    {
        return new self(['status' => $status->value]);
    }

    public static function addEntry(CreateProjectContractLineEntry $entry): self
    {
        return new self(['projectContractLineEntries' => [$entry->toArray()]]);
    }

    public static function removeEntry(ObjectKey $key): self
    {
        return new self(['projectContractLineEntries' => [['ia::operation' => 'delete', 'key' => $key->value]]]);
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

    public function withName(string $name): self
    {
        Assert::notBlank($name, 'The project contract line name');

        return $this->with('name', $name);
    }

    public function withDescription(?string $description): self
    {
        return $this->with('description', $description);
    }

    public function withStatus(RecordStatus $status): self
    {
        return $this->with('status', $status->value);
    }

    public function withParent(?ObjectReference $parent): self
    {
        return $this->with('parent', $parent?->toWriteArray());
    }

    public function withContractLineDate(?LocalDate $contractLineDate): self
    {
        return $this->with('contractLineDate', $contractLineDate?->value);
    }

    public function withGlAccount(?ObjectReference $glAccount): self
    {
        return $this->with('glAccount', $glAccount?->toWriteArray());
    }

    public function withRetainagePercentage(?Decimal $retainagePercentage): self
    {
        return $this->with('retainagePercentage', $retainagePercentage?->value);
    }

    public function withBillable(bool $billable): self
    {
        return $this->with('isBillable', $billable);
    }

    public function withExcludeFromGlBudget(bool $exclude): self
    {
        return $this->with('excludeFromGLBudget', $exclude);
    }

    public function withBillingType(ProjectContractLineBillingType $billingType): self
    {
        return $this->withBillingSetup(['billingType' => $billingType->value]);
    }

    /** A specified-amount maximum requires $amount; for the other options it is cleared. */
    public function withMaximumBilling(ProjectContractLineMaximumBilling $maximumBilling, ?Decimal $amount = null): self
    {
        if ($maximumBilling === ProjectContractLineMaximumBilling::SpecifiedAmount && $amount === null) {
            throw new InvalidArgument('A specified-amount maximum billing requires a maximum billing amount.');
        }

        return $this->withBillingSetup([
            'maximumBilling' => $maximumBilling->value,
            'maximumBillingAmount' => $amount?->value,
        ]);
    }

    public function withSummarizeBill(bool $summarizeBill): self
    {
        return $this->withBillingSetup(['summarizeBill' => $summarizeBill]);
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

    /** Sets the schedule dates that are non-null; dates left null keep their current value. */
    public function withSchedule(ProjectContractSchedule $schedule): self
    {
        return $this->withGroup('schedule', $schedule->toArray());
    }

    public function withInternalReferenceNumber(?string $referenceNumber): self
    {
        return $this->with('internalReference', ['referenceNumber' => $referenceNumber]);
    }

    public function withExternalReferenceNumber(?string $referenceNumber): self
    {
        return $this->with('externalReference', ['referenceNumber' => $referenceNumber]);
    }

    /** Sends the dimensions as given; a null user-defined dimension clears it. */
    public function withDimensions(Dimensions $dimensions): self
    {
        return $this->with('dimensions', $dimensions->toWriteArray());
    }

    public function withAddedEntry(CreateProjectContractLineEntry $entry): self
    {
        return $this->withEntry($entry->toArray());
    }

    public function withRemovedEntry(ObjectKey $key): self
    {
        return $this->withEntry(['ia::operation' => 'delete', 'key' => $key->value]);
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

    /** @param array<string, mixed> $fields */
    private function withBillingSetup(array $fields): self
    {
        return $this->withGroup('billingSetup', $fields);
    }

    /**
     * Fields of one group share a nested object in the PATCH body.
     *
     * @param  array<string, mixed>  $fields
     */
    private function withGroup(string $group, array $fields): self
    {
        $current = $this->changes[$group] ?? [];

        return $this->with($group, [...(is_array($current) ? $current : []), ...$fields]);
    }

    /** @param array<string, mixed> $entry */
    private function withEntry(array $entry): self
    {
        $entries = $this->changes['projectContractLineEntries'] ?? [];

        return $this->with('projectContractLineEntries', [...(is_array($entries) ? array_values($entries) : []), $entry]);
    }
}
