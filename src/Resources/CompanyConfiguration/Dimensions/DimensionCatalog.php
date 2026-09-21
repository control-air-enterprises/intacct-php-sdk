<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Dimensions;

use ControlAir\Intacct\Core\Response\ResponseMeta;

final readonly class DimensionCatalog
{
    /** @param list<DimensionDefinition> $dimensions */
    public function __construct(
        public array $dimensions,
        public ResponseMeta $meta,
    ) {}

    public function find(string $name): ?DimensionDefinition
    {
        foreach ($this->dimensions as $dimension) {
            if ($dimension->name === $name) {
                return $dimension;
            }
        }

        return null;
    }
}
