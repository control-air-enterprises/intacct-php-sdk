<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Model;

use ControlAir\Intacct\Support\ArrayReader;

/**
 * The model of one resource from `GET services/core/model?name=...`.
 *
 * Objects populate fields, groups, refs and lists directly. Services and
 * workflows instead describe their payloads in `request` and `response`,
 * each of which is itself an ObjectModel.
 */
final readonly class ObjectModel
{
    /**
     * @param  array<string, FieldDefinition>  $fields
     * @param  array<string, GroupDefinition>  $groups
     * @param  array<string, RelationshipDefinition>  $refs
     * @param  array<string, RelationshipDefinition>  $lists
     * @param  list<string>  $httpMethods
     */
    public function __construct(
        public array $fields = [],
        public array $groups = [],
        public array $refs = [],
        public array $lists = [],
        public array $httpMethods = [],
        public ?string $apiObject = null,
        public ?string $type = null,
        public ?string $href = null,
        public ?bool $idempotenceSupported = null,
        public ?ObjectModel $request = null,
        public ?ObjectModel $response = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $request = ArrayReader::object($data['request'] ?? null);
        $response = ArrayReader::object($data['response'] ?? null);

        return new self(
            fields: ModelMapper::fields($data['fields'] ?? null),
            groups: ModelMapper::groups($data['groups'] ?? null),
            refs: ModelMapper::relationships($data['refs'] ?? null),
            lists: ModelMapper::relationships($data['lists'] ?? null),
            httpMethods: ModelMapper::httpMethods($data['httpMethods'] ?? null),
            apiObject: ArrayReader::string($data, 'apiObject'),
            type: ArrayReader::string($data, 'type'),
            href: ArrayReader::string($data, 'href'),
            idempotenceSupported: ArrayReader::bool($data, 'idempotenceSupported'),
            request: $request === null ? null : self::fromArray($request),
            response: $response === null ? null : self::fromArray($response),
        );
    }

    /**
     * Finds a field by name, or by dotted path into a group or ref, e.g.
     * `audit.createdBy` or `parent.id` (the notation the query service uses).
     */
    public function field(string $path): ?FieldDefinition
    {
        if (isset($this->fields[$path])) {
            return $this->fields[$path];
        }

        $separator = strpos($path, '.');

        if ($separator === false) {
            return null;
        }

        $owner = substr($path, 0, $separator);
        $name = substr($path, $separator + 1);

        return ($this->groups[$owner] ?? null)?->field($name)
            ?? ($this->refs[$owner] ?? null)?->field($name);
    }

    /**
     * Top-level `nsp::` fields, which is where custom fields appear.
     *
     * @return list<FieldDefinition>
     */
    public function customFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (FieldDefinition $field): bool => $field->isCustom(),
        ));
    }

    /**
     * `nsp::` refs, such as custom relationship fields.
     *
     * @return list<RelationshipDefinition>
     */
    public function customRelationships(): array
    {
        return array_values(array_filter(
            $this->refs,
            static fn (RelationshipDefinition $ref): bool => $ref->isCustom(),
        ));
    }

    public function supportsMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->httpMethods, true);
    }

    public function isServiceOrWorkflow(): bool
    {
        return $this->request !== null || $this->response !== null;
    }
}
