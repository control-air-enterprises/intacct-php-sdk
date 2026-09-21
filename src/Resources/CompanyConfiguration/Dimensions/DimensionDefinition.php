<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Dimensions;

use ControlAir\Intacct\Support\ArrayReader;

final readonly class DimensionDefinition
{
    public function __construct(
        public string $name,
        public string $label,
        public string $term,
        public bool $userDefined,
        public bool $enabledInGeneralLedger,
        public string $endpoint,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: ArrayReader::requiredString($data, 'dimensionName'),
            label: ArrayReader::requiredString($data, 'dimensionLabel'),
            term: ArrayReader::requiredString($data, 'termName'),
            userDefined: ArrayReader::bool($data, 'isUserDefinedDimension') ?? false,
            enabledInGeneralLedger: ArrayReader::bool($data, 'isEnabledInGL') ?? false,
            endpoint: ArrayReader::requiredString($data, 'dimensionEndpoint'),
        );
    }
}
