<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContracts;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class UpdateProjectContract
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function name(string $name): self
    {
        Assert::notBlank($name, 'The project contract name');

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
        Assert::notBlank($name, 'The project contract name');

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

    public function withContractDate(?LocalDate $contractDate): self
    {
        return $this->with('contractDate', $contractDate?->value);
    }

    public function withProject(?ObjectReference $project): self
    {
        return $this->with('project', $project?->toWriteArray());
    }

    public function withCustomer(?ObjectReference $customer): self
    {
        return $this->with('customer', $customer?->toWriteArray());
    }

    public function withProjectContractType(?ObjectReference $projectContractType): self
    {
        return $this->with('projectContractType', $projectContractType?->toWriteArray());
    }

    public function withLocation(?ObjectReference $location): self
    {
        return $this->with('location', $location?->toWriteArray());
    }

    public function withBillable(bool $billable): self
    {
        return $this->with('isBillable', $billable);
    }

    public function withExcludeFromWipReporting(bool $exclude): self
    {
        return $this->with('excludeFromWIPReporting', $exclude);
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
        $current = $this->changes['schedule'] ?? [];

        return $this->with('schedule', [...(is_array($current) ? $current : []), ...$schedule->toArray()]);
    }

    public function withInternalReferenceNumber(?string $referenceNumber): self
    {
        return $this->with('internalReference', ['referenceNumber' => $referenceNumber]);
    }

    public function withExternalReferenceNumber(?string $referenceNumber): self
    {
        return $this->with('externalReference', ['referenceNumber' => $referenceNumber]);
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
