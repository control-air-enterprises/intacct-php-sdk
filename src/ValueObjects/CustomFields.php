<?php

declare(strict_types=1);

namespace ControlAir\Intacct\ValueObjects;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\Support\Assert;

/**
 * Custom field values keyed by their `nsp::` integration name.
 *
 * Sage returns custom fields as top-level `nsp::` keys beside the standard fields, and
 * user-defined dimensions as `nsp::` keys inside a line's `dimensions` object. The prefix
 * is required on read and write, so names are normalized to carry it: `INDIRECT` and
 * `nsp::INDIRECT` name the same field.
 *
 * A value is a scalar, null, a list of scalars (multi-picklists) or an object reference
 * (relationship fields and user-defined dimensions).
 *
 * @phpstan-type CustomFieldValue bool|int|float|string|list<bool|int|float|string>|ObjectReference|null
 * @phpstan-type CustomFieldWriteValue bool|int|float|string|list<bool|int|float|string>|array{key: string}|array{id: string}|null
 */
final readonly class CustomFields
{
    public const PREFIX = 'nsp::';

    /** @var array<string, CustomFieldValue> */
    private array $values;

    /**
     * @param  array<string, mixed>  $values  Values keyed by field name, with or without the prefix.
     */
    public function __construct(array $values = [])
    {
        $normalized = [];

        foreach ($values as $name => $value) {
            $name = self::name((string) $name);
            $normalized[$name] = self::value($name, $value);
        }

        $this->values = $normalized;
    }

    /**
     * Reads the `nsp::` keys of a response object and ignores the standard fields.
     *
     * Relationship objects become references, or null when they carry neither a key nor
     * an ID. Values of any other shape are skipped.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $values = [];

        foreach ($data as $field => $value) {
            $field = (string) $field;

            if (! str_starts_with($field, self::PREFIX) || trim(substr($field, strlen(self::PREFIX))) === '') {
                continue;
            }

            $object = ArrayReader::object($value);

            if ($object !== null) {
                if (array_key_exists('key', $object) || array_key_exists('id', $object)) {
                    $values[$field] = ObjectReference::fromArray($object);
                }

                continue;
            }

            if (self::isWritable($value)) {
                $values[$field] = $value;
            }
        }

        return new self($values);
    }

    /** Returns the field name with the `nsp::` prefix. */
    public static function name(string $name): string
    {
        $bare = str_starts_with($name, self::PREFIX) ? substr($name, strlen(self::PREFIX)) : $name;

        Assert::notBlank($bare, 'A custom field name');

        return self::PREFIX.$bare;
    }

    /** Returns a copy with the field set; null clears the field when written. */
    public function with(string $name, mixed $value): self
    {
        return new self([...$this->values, self::name($name) => $value]);
    }

    /** @return CustomFieldValue */
    public function get(string $name): mixed
    {
        return $this->values[self::name($name)] ?? null;
    }

    public function has(string $name): bool
    {
        return array_key_exists(self::name($name), $this->values);
    }

    /**
     * The reference held by a relationship field, or null when it is empty or absent.
     *
     * @throws InvalidArgument when the field holds a value that is not a reference
     */
    public function reference(string $name): ?ObjectReference
    {
        $value = $this->get($name);

        if ($value === null || $value instanceof ObjectReference) {
            return $value;
        }

        throw new InvalidArgument(sprintf('The custom field "%s" does not hold an object reference.', self::name($name)));
    }

    /** @return array<string, CustomFieldValue> */
    public function all(): array
    {
        return $this->values;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /** @return array<string, CustomFieldWriteValue> */
    public function toWriteArray(): array
    {
        return array_map(
            static fn (mixed $value): mixed => $value instanceof ObjectReference ? $value->toWriteArray() : $value,
            $this->values,
        );
    }

    /** @return CustomFieldValue */
    private static function value(string $name, mixed $value): mixed
    {
        if ($value instanceof ObjectReference || self::isWritable($value)) {
            return $value;
        }

        throw new InvalidArgument(sprintf(
            'The custom field "%s" must be a scalar, null, a list of scalars or an object reference.',
            $name,
        ));
    }

    /** @phpstan-assert-if-true bool|int|float|string|list<bool|int|float|string>|null $value */
    private static function isWritable(mixed $value): bool
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                return false;
            }

            foreach ($value as $item) {
                if (! self::isScalar($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || self::isScalar($value);
    }

    /** @phpstan-assert-if-true bool|int|float|string $value */
    private static function isScalar(mixed $value): bool
    {
        if (is_float($value)) {
            return is_finite($value);
        }

        return is_bool($value) || is_int($value) || is_string($value);
    }
}
