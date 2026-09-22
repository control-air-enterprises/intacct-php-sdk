<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Model;

/**
 * A named group of fields, such as `audit`. Nested refs (for example the
 * `dimensions` group on transaction lines) are mapped when present.
 */
final readonly class GroupDefinition
{
    /**
     * @param  array<string, FieldDefinition>  $fields
     * @param  array<string, RelationshipDefinition>  $refs
     */
    public function __construct(
        public string $name,
        public array $fields = [],
        public array $refs = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(string $name, array $data): self
    {
        return new self(
            name: $name,
            fields: ModelMapper::fields($data['fields'] ?? null),
            refs: ModelMapper::relationships($data['refs'] ?? null),
        );
    }

    public function field(string $name): ?FieldDefinition
    {
        return $this->fields[$name] ?? null;
    }

    /** @return list<FieldDefinition> */
    public function customFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (FieldDefinition $field): bool => $field->isCustom(),
        ));
    }
}
