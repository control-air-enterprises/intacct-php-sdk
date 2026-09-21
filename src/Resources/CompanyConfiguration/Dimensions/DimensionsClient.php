<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Dimensions;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\HttpMethod;
use ControlAir\Intacct\Core\Response\ResponseMeta;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;

final readonly class DimensionsClient
{
    public function __construct(private ApiTransport $transport) {}

    public function list(): DimensionCatalog
    {
        $payload = $this->transport->request(
            HttpMethod::Get,
            'services/company-config/dimensions/list',
        );

        $result = $payload['ia::result'] ?? null;
        $meta = ArrayReader::object($payload['ia::meta'] ?? null) ?? [];

        if (! is_array($result) || ! array_is_list($result)) {
            throw new MappingException('The dimension response does not contain an ia::result list.');
        }

        $dimensions = [];

        foreach ($result as $item) {
            if (! is_array($item)) {
                throw new MappingException('A dimension result item is not an object.');
            }

            /** @var array<string, mixed> $item */
            $dimensions[] = DimensionDefinition::fromArray($item);
        }

        return new DimensionCatalog(
            $dimensions,
            ResponseMeta::fromArray($meta),
        );
    }
}
