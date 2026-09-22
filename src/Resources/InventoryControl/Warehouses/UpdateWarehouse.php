<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\Warehouses;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class UpdateWarehouse
{
    /** @param non-empty-array<string, mixed> $changes */
    private function __construct(private array $changes) {}

    public static function name(string $name): self
    {
        Assert::notBlank($name, 'The warehouse name');

        return new self(['name' => $name]);
    }

    public static function status(RecordStatus $status): self
    {
        return new self(['status' => $status->value]);
    }

    public function withStatus(RecordStatus $status): self
    {
        return $this->with('status', $status->value);
    }

    public function withLocation(ObjectReference $location): self
    {
        return $this->with('location', $location->toWriteArray());
    }

    public function withParent(?ObjectReference $parent): self
    {
        return $this->with('parent', $parent?->toWriteArray());
    }

    public function withManager(?ObjectReference $manager): self
    {
        return $this->with('manager', $manager?->toWriteArray());
    }

    public function withReplenishmentEnabled(bool $enabled): self
    {
        return $this->with('isReplenishmentEnabled', $enabled);
    }

    public function withNegativeInventoryEnabled(bool $enabled): self
    {
        return $this->with('enableNegativeInv', $enabled);
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
