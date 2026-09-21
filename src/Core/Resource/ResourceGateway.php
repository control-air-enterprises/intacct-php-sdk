<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Resource;

use Closure;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\HttpMethod;
use ControlAir\Intacct\Core\Query\Query;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\ValueObjects\ObjectKey;

/** @template T of object */
final readonly class ResourceGateway
{
    /**
     * @param  Closure(array<string, mixed>): T  $mapper
     */
    public function __construct(
        private ApiTransport $transport,
        private QueryClient $queries,
        private string $path,
        private string $object,
        private Closure $mapper,
    ) {}

    /** @return T */
    public function get(ObjectKey $key): object
    {
        $payload = $this->transport->request(
            HttpMethod::Get,
            $this->path.'/'.rawurlencode($key->value),
        );

        return ($this->mapper)($this->singleResult($payload));
    }

    /** @return Page<T> */
    public function query(Query $query): Page
    {
        $page = $this->queries->execute($this->object, $query);
        $items = [];

        foreach ($page->items as $item) {
            $items[] = ($this->mapper)($item);
        }

        return new Page($items, $page->meta);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): MutationResult
    {
        return MutationResult::fromPayload(
            $this->transport->request(HttpMethod::Post, $this->path, json: $data),
        );
    }

    /** @param array<string, mixed> $data */
    public function update(ObjectKey $key, array $data): MutationResult
    {
        return MutationResult::fromPayload(
            $this->transport->request(
                HttpMethod::Patch,
                $this->path.'/'.rawurlencode($key->value),
                json: $data,
            ),
        );
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return MutationResult::fromPayload(
            $this->transport->request(
                HttpMethod::Delete,
                $this->path.'/'.rawurlencode($key->value),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function singleResult(array $payload): array
    {
        $result = $payload['ia::result'] ?? null;

        if (! is_array($result) || array_is_list($result)) {
            throw new MappingException('The object response does not contain an ia::result object.');
        }

        /** @var array<string, mixed> $result */
        return $result;
    }
}
