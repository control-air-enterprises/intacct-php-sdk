<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Model;

use ControlAir\Intacct\Support\ArrayReader;

/** The operations the current user may perform on one record. */
final readonly class AllowedOperations
{
    /**
     * @param  list<string>  $operations
     * @param  array<string, mixed>  $additionalData
     */
    public function __construct(
        public string $key,
        public array $operations = [],
        public array $additionalData = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $operations = [];
        $values = $data['operations'] ?? null;

        if (is_array($values)) {
            foreach ($values as $operation) {
                if (is_string($operation) && $operation !== '') {
                    $operations[] = $operation;
                }
            }
        }

        return new self(
            key: ArrayReader::requiredString($data, 'key'),
            operations: $operations,
            additionalData: ArrayReader::object($data['additionalData'] ?? null) ?? [],
        );
    }

    public function allows(string $operation): bool
    {
        return in_array($operation, $this->operations, true);
    }
}
