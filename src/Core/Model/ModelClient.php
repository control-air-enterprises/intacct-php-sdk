<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Model;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\HttpMethod;
use ControlAir\Intacct\Core\Response\ResponseMeta;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\ObjectKey;

/**
 * Object model introspection: lists resources, describes their fields
 * (including company-specific `nsp::` custom fields) and checks which
 * operations the current user may perform on records.
 */
final readonly class ModelClient
{
    private const MAX_OPERATION_KEYS = 1000;

    public function __construct(private ApiTransport $transport) {}

    /**
     * Lists the available resources.
     *
     * @param  string|null  $type  `object`, `service`, `workflow`, a resource type such as `ownedObject`,
     *                             or a comma-separated list of resource types.
     * @param  string|null  $filter  A regex on resource names, e.g. `.*company-config\/cla.*`.
     * @param  string|null  $version  e.g. `v1`. Defaults to the version in the base URL.
     * @param  string|null  $descriptionFilter  A regex on resource descriptions.
     */
    public function list(
        ?string $type = null,
        ?string $filter = null,
        ?string $version = null,
        ?string $descriptionFilter = null,
    ): ResourceCatalog {
        $payload = $this->transport->request(
            HttpMethod::Get,
            'services/core/model',
            query: self::query([
                'type' => $type,
                'version' => $version,
                'filter' => $filter,
                'descriptionFilter' => $descriptionFilter,
            ]),
        );

        $result = $payload['ia::result'] ?? null;
        $meta = ArrayReader::object($payload['ia::meta'] ?? null) ?? [];

        if (! is_array($result) || ! array_is_list($result)) {
            throw new MappingException('The model response does not contain an ia::result list.');
        }

        $resources = [];

        foreach ($result as $item) {
            $item = ArrayReader::object($item)
                ?? throw new MappingException('A model result item is not an object.');

            $resources[] = ResourceSummary::fromArray($item);
        }

        return new ResourceCatalog($resources, ResponseMeta::fromArray($meta));
    }

    /**
     * Describes one resource, e.g. `accounts-payable/vendor` or
     * `platform-apps/nsp::travel_UDD`. Returns null when Sage reports the
     * resource as not found (an empty result list).
     *
     * @param  bool  $descriptions  Also return descriptions, examples and tags.
     */
    public function describe(
        string $name,
        ?string $type = null,
        ?string $version = null,
        bool $descriptions = false,
    ): ?ObjectModel {
        Assert::notBlank($name, 'The model name');

        $payload = $this->transport->request(
            HttpMethod::Get,
            'services/core/model',
            query: self::query([
                'name' => $name,
                'type' => $type,
                'version' => $version,
                'description' => $descriptions ? 'true' : null,
            ]),
        );

        $result = $payload['ia::result'] ?? null;

        if ($result === []) {
            return null;
        }

        $model = ArrayReader::object($result)
            ?? throw new MappingException('The model response does not contain an ia::result object.');

        return ObjectModel::fromArray($model);
    }

    /**
     * Checks which operations the current user may perform on records.
     *
     * @param  string  $object  e.g. `accounts-payable/vendor`.
     * @param  list<ObjectKey|string>  $keys  Between 1 and 1000 record keys.
     * @param  list<string>  $operations  e.g. `canView`, `canEdit`, `canDelete`. Empty sends none.
     * @param  array<string, mixed>  $additionalData
     */
    public function allowedOperations(
        string $object,
        array $keys,
        array $operations = [],
        ?bool $includePrivate = null,
        ?string $moduleKey = null,
        array $additionalData = [],
    ): AllowedOperationsResult {
        Assert::notBlank($object, 'The allowed-operations object');

        if ($keys === [] || count($keys) > self::MAX_OPERATION_KEYS) {
            throw new InvalidArgument(sprintf(
                'Allowed operations require between 1 and %d keys.',
                self::MAX_OPERATION_KEYS,
            ));
        }

        $body = [
            'object' => $object,
            'keys' => array_map(static function (ObjectKey|string $key): string {
                $key = (string) $key;
                Assert::notBlank($key, 'An allowed-operations key');

                return $key;
            }, $keys),
        ];

        if ($operations !== []) {
            $body['operations'] = $operations;
        }

        $options = array_filter(
            ['includePrivate' => $includePrivate, 'moduleKey' => $moduleKey],
            static fn (bool|string|null $value): bool => $value !== null,
        );

        if ($options !== []) {
            $body['options'] = $options;
        }

        if ($additionalData !== []) {
            $body['additionalData'] = $additionalData;
        }

        $payload = $this->transport->request(
            HttpMethod::Post,
            'services/core/allowed-operations/list',
            json: $body,
        );

        $result = $payload['ia::result'] ?? null;
        $meta = ArrayReader::object($payload['ia::meta'] ?? null) ?? [];

        if (! is_array($result) || ! array_is_list($result)) {
            throw new MappingException('The allowed-operations response does not contain an ia::result list.');
        }

        $records = [];

        foreach ($result as $item) {
            $item = ArrayReader::object($item)
                ?? throw new MappingException('An allowed-operations result item is not an object.');

            $records[] = AllowedOperations::fromArray($item);
        }

        return new AllowedOperationsResult($records, ResponseMeta::fromArray($meta));
    }

    /**
     * @param  array<string, string|null>  $parameters
     * @return array<string, string>
     */
    private static function query(array $parameters): array
    {
        return array_filter(
            $parameters,
            static fn (?string $value): bool => $value !== null && $value !== '',
        );
    }
}
