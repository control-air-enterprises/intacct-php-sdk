<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Model;

use ControlAir\Intacct\Support\ArrayReader;

/**
 * Tolerant helpers shared by the model DTOs.
 *
 * Object models key groups, refs and lists by name, while service and
 * workflow models send them as arrays, so both shapes are accepted.
 *
 * @internal
 */
final class ModelMapper
{
    private const DESCRIPTOR_KEYS = ['apiObject', 'fields', 'groups', 'refs', 'lists', 'type', 'httpMethods'];

    /** @return array<string, FieldDefinition> */
    public static function fields(mixed $value): array
    {
        $fields = [];

        foreach (self::entries($value) as $name => $descriptor) {
            $fields[$name] = FieldDefinition::fromArray($name, $descriptor);
        }

        return $fields;
    }

    /** @return array<string, GroupDefinition> */
    public static function groups(mixed $value): array
    {
        $groups = [];

        foreach (self::entries($value) as $name => $descriptor) {
            $groups[$name] = GroupDefinition::fromArray($name, $descriptor);
        }

        return $groups;
    }

    /** @return array<string, RelationshipDefinition> */
    public static function relationships(mixed $value): array
    {
        $relationships = [];

        foreach (self::entries($value) as $name => $descriptor) {
            $relationships[$name] = RelationshipDefinition::fromArray($name, $descriptor);
        }

        return $relationships;
    }

    /**
     * Accepts `"OPTIONS,GET,POST"` or `["OPTIONS", "GET", "POST"]`.
     *
     * @return list<string>
     */
    public static function httpMethods(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $methods = [];

        foreach ($value as $method) {
            if (is_string($method) && trim($method) !== '') {
                $methods[] = strtoupper(trim($method));
            }
        }

        return $methods;
    }

    /**
     * Normalizes a name-keyed map, or a list of named entries, to a map.
     *
     * List entries are named by their `name` key, by a single `{name: {...}}`
     * wrapper, or by their `apiObject`. Entries that cannot be named, and
     * values that are not objects, are skipped.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function entries(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $entries = [];

        if (! array_is_list($value)) {
            foreach ($value as $name => $item) {
                $descriptor = ArrayReader::object($item);

                if (is_string($name) && $descriptor !== null) {
                    $entries[$name] = $descriptor;
                }
            }

            return $entries;
        }

        foreach ($value as $item) {
            $descriptor = ArrayReader::object($item);

            if ($descriptor === null) {
                continue;
            }

            $name = ArrayReader::string($descriptor, 'name');

            if ($name !== null) {
                $entries[$name] = $descriptor;

                continue;
            }

            $wrapped = self::unwrap($descriptor);

            if ($wrapped !== null) {
                $entries[$wrapped[0]] = $wrapped[1];

                continue;
            }

            $apiObject = ArrayReader::string($descriptor, 'apiObject');

            if ($apiObject !== null) {
                $entries[$apiObject] = $descriptor;
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $descriptor
     * @return array{string, array<string, mixed>}|null
     */
    private static function unwrap(array $descriptor): ?array
    {
        if (count($descriptor) !== 1) {
            return null;
        }

        $name = array_key_first($descriptor);

        if (in_array($name, self::DESCRIPTOR_KEYS, true)) {
            return null;
        }

        $inner = ArrayReader::object($descriptor[$name]);

        return $inner === null ? null : [$name, $inner];
    }

    private function __construct() {}
}
