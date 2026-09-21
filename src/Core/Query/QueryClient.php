<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Query;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\HttpMethod;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\Core\Response\ResponseMeta;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;

final readonly class QueryClient
{
    public function __construct(private ApiTransport $transport) {}

    /** @return Page<array<string, mixed>> */
    public function execute(string $object, Query $query): Page
    {
        $payload = $this->transport->request(
            HttpMethod::Post,
            'services/core/query',
            json: $query->forObject($object),
        );

        $result = $payload['ia::result'] ?? null;
        $meta = ArrayReader::object($payload['ia::meta'] ?? null) ?? [];

        if (! is_array($result) || ! array_is_list($result)) {
            throw new MappingException('The query response does not contain an ia::result list.');
        }

        $items = [];

        foreach ($result as $item) {
            if (! is_array($item)) {
                throw new MappingException('A query result item is not an object.');
            }

            /** @var array<string, mixed> $item */
            $items[] = $item;
        }

        return new Page(
            $items,
            ResponseMeta::fromArray($meta),
        );
    }
}
