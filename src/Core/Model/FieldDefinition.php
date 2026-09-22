<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Model;

use ControlAir\Intacct\Support\ArrayReader;

/**
 * A field descriptor from the model service.
 *
 * Sage documents that `required` is always reported as false, so required
 * fields must come from the object's reference documentation instead.
 */
final readonly class FieldDefinition
{
    public const CUSTOM_PREFIX = 'nsp::';

    /**
     * @param  list<scalar|null>  $enumValues
     * @param  array<string, mixed>  $attributes  The full descriptor, including keys the SDK does not map.
     */
    public function __construct(
        public string $name,
        public ?string $type = null,
        public ?string $format = null,
        public array $enumValues = [],
        public bool $readOnly = false,
        public bool $writeOnly = false,
        public bool $required = false,
        public bool $nullable = false,
        public bool $mutable = true,
        public ?string $description = null,
        public array $attributes = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(string $name, array $data): self
    {
        $readOnly = ArrayReader::bool($data, 'readOnly') ?? false;

        return new self(
            name: $name,
            type: ArrayReader::string($data, 'type'),
            format: ArrayReader::string($data, 'format'),
            enumValues: self::enumValues($data['enum'] ?? null),
            readOnly: $readOnly,
            writeOnly: ArrayReader::bool($data, 'writeOnly') ?? false,
            required: ArrayReader::bool($data, 'required') ?? false,
            nullable: ArrayReader::bool($data, 'nullable') ?? false,
            mutable: ArrayReader::bool($data, 'mutable') ?? ! $readOnly,
            description: ArrayReader::string($data, 'description'),
            attributes: $data,
        );
    }

    /** Custom fields are reported beside standard fields with the `nsp::` prefix. */
    public function isCustom(): bool
    {
        return str_starts_with($this->name, self::CUSTOM_PREFIX);
    }

    public function isEnum(): bool
    {
        return $this->enumValues !== [];
    }

    /** @return list<scalar|null> */
    private static function enumValues(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        $values = [];

        foreach ($value as $item) {
            if ($item === null || is_scalar($item)) {
                $values[] = $item;
            }
        }

        return $values;
    }
}
