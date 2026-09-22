<?php

declare(strict_types=1);

namespace ControlAir\Intacct\ValueObjects;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\ArrayReader;

final readonly class Dimensions
{
    /**
     * @param  CustomFields  $custom  User-defined dimensions, the `nsp::` keys of the dimensions object.
     */
    public function __construct(
        public ?ObjectReference $location = null,
        public ?ObjectReference $department = null,
        public ?ObjectReference $employee = null,
        public ?ObjectReference $project = null,
        public ?ObjectReference $customer = null,
        public ?ObjectReference $vendor = null,
        public ?ObjectReference $item = null,
        public ?ObjectReference $warehouse = null,
        public ?ObjectReference $class = null,
        public ?ObjectReference $task = null,
        public ?ObjectReference $costType = null,
        public CustomFields $custom = new CustomFields,
    ) {
        foreach ($this->custom->all() as $name => $value) {
            if ($value !== null && ! $value instanceof ObjectReference) {
                throw new InvalidArgument(sprintf(
                    'The user-defined dimension "%s" must be an object reference or null.',
                    $name,
                ));
            }
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            location: ArrayReader::reference($data, 'location'),
            department: ArrayReader::reference($data, 'department'),
            employee: ArrayReader::reference($data, 'employee'),
            project: ArrayReader::reference($data, 'project'),
            customer: ArrayReader::reference($data, 'customer'),
            vendor: ArrayReader::reference($data, 'vendor'),
            item: ArrayReader::reference($data, 'item'),
            warehouse: ArrayReader::reference($data, 'warehouse'),
            class: ArrayReader::reference($data, 'class'),
            task: ArrayReader::reference($data, 'task'),
            costType: ArrayReader::reference($data, 'costType'),
            custom: new CustomFields(array_filter(
                CustomFields::fromArray($data)->all(),
                static fn (mixed $value): bool => $value === null || $value instanceof ObjectReference,
            )),
        );
    }

    /** The user-defined dimension with this integration name, with or without the `nsp::` prefix. */
    public function userDefined(string $name): ?ObjectReference
    {
        return $this->custom->reference($name);
    }

    /** @return array<string, array{key: string}|array{id: string}|null> */
    public function toWriteArray(): array
    {
        $standard = array_filter([
            'location' => $this->location?->toWriteArray(),
            'department' => $this->department?->toWriteArray(),
            'employee' => $this->employee?->toWriteArray(),
            'project' => $this->project?->toWriteArray(),
            'customer' => $this->customer?->toWriteArray(),
            'vendor' => $this->vendor?->toWriteArray(),
            'item' => $this->item?->toWriteArray(),
            'warehouse' => $this->warehouse?->toWriteArray(),
            'class' => $this->class?->toWriteArray(),
            'task' => $this->task?->toWriteArray(),
            'costType' => $this->costType?->toWriteArray(),
        ], static fn (?array $value): bool => $value !== null);

        // A null user-defined dimension is written as null so a PATCH clears it.
        foreach ($this->custom->all() as $name => $value) {
            if ($value === null || $value instanceof ObjectReference) {
                $standard[$name] = $value?->toWriteArray();
            }
        }

        return $standard;
    }
}
