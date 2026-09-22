<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Model;

use ControlAir\Intacct\Support\ArrayReader;

/**
 * A reference to another object (`refs`) or a repeatable owned collection
 * (`lists`), with the related object's name and its exposed fields.
 */
final readonly class RelationshipDefinition
{
    /** @param array<string, FieldDefinition> $fields */
    public function __construct(
        public string $name,
        public ?string $apiObject = null,
        public array $fields = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(string $name, array $data): self
    {
        return new self(
            name: $name,
            apiObject: ArrayReader::string($data, 'apiObject'),
            fields: ModelMapper::fields($data['fields'] ?? null),
        );
    }

    /** Custom relationship fields and UDDs use the `nsp::` prefix, e.g. `nsp::r10258`. */
    public function isCustom(): bool
    {
        return str_starts_with($this->name, FieldDefinition::CUSTOM_PREFIX);
    }

    public function field(string $name): ?FieldDefinition
    {
        return $this->fields[$name] ?? null;
    }
}
