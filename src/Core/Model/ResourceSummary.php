<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Model;

use ControlAir\Intacct\Support\ArrayReader;

/** One entry of the resource list from `GET services/core/model`. */
final readonly class ResourceSummary
{
    /** @param list<string> $httpMethods */
    public function __construct(
        public string $apiObject,
        public ?string $type = null,
        public array $httpMethods = [],
        public ?string $href = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            apiObject: ArrayReader::requiredString($data, 'apiObject'),
            type: ArrayReader::string($data, 'type'),
            httpMethods: ModelMapper::httpMethods($data['httpMethods'] ?? null),
            href: ArrayReader::string($data, 'href'),
        );
    }

    /**
     * The resource name without the `objects/` prefix, e.g.
     * `company-config/department`, as accepted by ModelClient::describe().
     */
    public function name(): string
    {
        return str_starts_with($this->apiObject, 'objects/')
            ? substr($this->apiObject, strlen('objects/'))
            : $this->apiObject;
    }

    /** Custom objects and UDDs are named `platform-apps/nsp::<name>`. */
    public function isCustom(): bool
    {
        return str_contains($this->apiObject, FieldDefinition::CUSTOM_PREFIX);
    }

    public function supportsMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->httpMethods, true);
    }
}
