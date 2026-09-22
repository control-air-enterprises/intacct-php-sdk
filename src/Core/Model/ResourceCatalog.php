<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Model;

use ControlAir\Intacct\Core\Response\ResponseMeta;

final readonly class ResourceCatalog
{
    /** @param list<ResourceSummary> $resources */
    public function __construct(
        public array $resources,
        public ResponseMeta $meta,
    ) {}

    /** Matches either the raw `apiObject` or the name without `objects/`. */
    public function find(string $name): ?ResourceSummary
    {
        foreach ($this->resources as $resource) {
            if ($resource->apiObject === $name || $resource->name() === $name) {
                return $resource;
            }
        }

        return null;
    }

    /** @return list<ResourceSummary> */
    public function ofType(string $type): array
    {
        return array_values(array_filter(
            $this->resources,
            static fn (ResourceSummary $resource): bool => $resource->type === $type,
        ));
    }

    /** @return list<ResourceSummary> */
    public function customObjects(): array
    {
        return array_values(array_filter(
            $this->resources,
            static fn (ResourceSummary $resource): bool => $resource->isCustom(),
        ));
    }

    public function isEmpty(): bool
    {
        return $this->resources === [];
    }
}
