<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Support;

use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final class ArrayReader
{
    /** @return array<string, mixed>|null */
    public static function object(mixed $value): ?array
    {
        if (! is_array($value) || array_is_list($value)) {
            return null;
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                return null;
            }

            $object[$key] = $item;
        }

        return $object;
    }

    /** @param array<string, mixed> $data */
    public static function requiredString(array $data, string $key): string
    {
        return self::string($data, $key)
            ?? throw new MappingException(sprintf('The response field "%s" must be a non-empty string.', $key));
    }

    /** @param array<string, mixed> $data */
    public static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    public static function bool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /** @param array<string, mixed> $data */
    public static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    /** @param array<string, mixed> $data */
    public static function key(array $data): ObjectKey
    {
        return new ObjectKey(self::requiredString($data, 'key'));
    }

    /** @param array<string, mixed> $data */
    public static function id(array $data): ObjectId
    {
        return new ObjectId(self::requiredString($data, 'id'));
    }

    /** @param array<string, mixed> $data */
    public static function reference(array $data, string $key): ?ObjectReference
    {
        $value = self::object($data[$key] ?? null);

        return $value === null ? null : ObjectReference::fromArray($value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<ObjectReference>
     */
    public static function references(array $data, string $key): array
    {
        $values = $data[$key] ?? null;

        if (! is_array($values) || ! array_is_list($values)) {
            return [];
        }

        $references = [];

        foreach ($values as $value) {
            $object = self::object($value);

            if ($object === null) {
                continue;
            }

            $reference = ObjectReference::fromArray($object);

            if ($reference !== null) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function list(array $data, string $key): array
    {
        $values = $data[$key] ?? null;

        if (! is_array($values) || ! array_is_list($values)) {
            return [];
        }

        $objects = [];

        foreach ($values as $value) {
            $object = self::object($value);

            if ($object !== null) {
                $objects[] = $object;
            }
        }

        return $objects;
    }

    /** @param array<string, mixed> $data */
    public static function date(array $data, string $key): ?LocalDate
    {
        $value = self::string($data, $key);

        return $value === null ? null : new LocalDate($value);
    }

    /** @param array<string, mixed> $data */
    public static function decimal(array $data, string $key): ?Decimal
    {
        $value = self::string($data, $key);

        return $value === null ? null : new Decimal($value);
    }

    private function __construct() {}
}
