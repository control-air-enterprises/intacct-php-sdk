<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Resource;

use Closure;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\HttpMethod;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Http\RequestHeaders;
use ControlAir\Intacct\Core\Query\Query;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\Core\Response\ResponseMeta;
use ControlAir\Intacct\Exceptions\ApiException;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\ObjectKey;

/** @template T of object */
final readonly class ResourceGateway
{
    public const MAX_BATCH_SIZE = 500;

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
            $items[] = ($this->mapper)(self::expandDottedKeys($item));
        }

        return new Page($items, $page->meta);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return MutationResult::fromPayload(
            $this->transport->request(
                HttpMethod::Post,
                $this->path,
                json: $data,
                headers: $idempotencyKey?->toHeaders() ?? [],
            ),
        );
    }

    /** @param array<string, mixed> $data */
    public function update(ObjectKey $key, array $data, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return MutationResult::fromPayload(
            $this->transport->request(
                HttpMethod::Patch,
                $this->path.'/'.rawurlencode($key->value),
                json: $data,
                headers: $idempotencyKey?->toHeaders() ?? [],
            ),
        );
    }

    public function delete(ObjectKey $key): MutationResult
    {
        $payload = $this->transport->request(
            HttpMethod::Delete,
            $this->path.'/'.rawurlencode($key->value),
        );

        // A successful DELETE answers 204 with an empty body.
        if (! array_key_exists('ia::result', $payload)) {
            return MutationResult::forDeletedKey(
                $key,
                ResponseMeta::fromArray(ArrayReader::object($payload['ia::meta'] ?? null) ?? []),
            );
        }

        return MutationResult::fromPayload($payload);
    }

    /**
     * Creates up to 500 records in one request by POSTing an array to the collection path.
     *
     * Batches are non-atomic by default: each record succeeds or fails on its own. With
     * $atomic, Sage Intacct rolls back the whole batch when any record fails.
     *
     * @param  list<array<string, mixed>>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        self::assertBatchSize($records);

        foreach ($records as $position => $record) {
            self::assertRecord($record, $position);
        }

        return $this->batch(
            HttpMethod::Post,
            $this->path,
            $records,
            array_fill(0, count($records), null),
            $atomic,
            $idempotencyKey,
        );
    }

    /**
     * Updates up to 500 records in one request by PATCHing an array to the collection path.
     * Each record must carry the key of the record it updates.
     *
     * @param  list<array<string, mixed>>  $records
     */
    public function updateMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        self::assertBatchSize($records);

        $body = [];
        $keys = [];

        foreach ($records as $position => $record) {
            self::assertRecord($record, $position);

            $key = self::recordKey($record, $position);
            $body[] = ['key' => $key->value] + $record;
            $keys[] = $key;
        }

        return $this->batch(HttpMethod::Patch, $this->path, $body, $keys, $atomic, $idempotencyKey);
    }

    /**
     * Deletes up to 500 records in one request, e.g. DELETE /objects/<app>/<object>/1,2,3.
     *
     * @param  list<ObjectKey>  $keys
     */
    public function deleteMany(array $keys, bool $atomic = false): BatchResult
    {
        self::assertBatchSize($keys);

        $segments = [];

        foreach ($keys as $position => $key) {
            if (str_contains($key->value, ',')) {
                throw new InvalidArgument(sprintf(
                    'The key at position %d contains a comma and cannot be deleted in a batch.',
                    $position,
                ));
            }

            if (isset($segments[$key->value])) {
                throw new InvalidArgument(sprintf(
                    'The key "%s" appears more than once; batch results are matched by position.',
                    $key->value,
                ));
            }

            $segments[$key->value] = rawurlencode($key->value);
        }

        return $this->batch(
            HttpMethod::Delete,
            $this->path.'/'.implode(',', $segments),
            null,
            $keys,
            $atomic,
            null,
        );
    }

    /**
     * @param  list<array<string, mixed>>|null  $body
     * @param  list<ObjectKey|null>  $requestedKeys
     */
    private function batch(
        HttpMethod $method,
        string $path,
        ?array $body,
        array $requestedKeys,
        bool $atomic,
        ?IdempotencyKey $idempotencyKey,
    ): BatchResult {
        $headers = $idempotencyKey?->toHeaders() ?? [];

        if ($atomic) {
            $headers[RequestHeaders::TRANSACTION] = 'true';
        }

        try {
            $response = $this->transport->send($method, $path, json: $body, headers: $headers);
        } catch (ApiException $exception) {
            // A batch whose records all failed may answer 4xx with per-record results.
            $result = $exception->response['ia::result'] ?? null;

            if (! is_array($result) || ! array_is_list($result) || count($result) !== count($requestedKeys)) {
                throw $exception;
            }

            return BatchResult::fromPayload($exception->response, $exception->statusCode, $requestedKeys);
        }

        return BatchResult::fromPayload(
            $response->payload,
            $response->statusCode,
            $requestedKeys,
            emptyMeansSuccess: $method === HttpMethod::Delete,
        );
    }

    /** @param array<mixed> $records */
    private static function assertBatchSize(array $records): void
    {
        if ($records === [] || count($records) > self::MAX_BATCH_SIZE) {
            throw new InvalidArgument(sprintf(
                'A batch must contain between 1 and %d records; %d given.',
                self::MAX_BATCH_SIZE,
                count($records),
            ));
        }

        if (! array_is_list($records)) {
            throw new InvalidArgument('Batch records must be a list so results can be matched by position.');
        }
    }

    /** @param array<string, mixed> $record */
    private static function assertRecord(array $record, int $position): void
    {
        if ($record === []) {
            throw new InvalidArgument(sprintf('The batch record at position %d must be a non-empty object.', $position));
        }
    }

    /** @param array<string, mixed> $record */
    private static function recordKey(array $record, int $position): ObjectKey
    {
        $key = $record['key'] ?? null;

        if ($key instanceof ObjectKey) {
            return $key;
        }

        if ((is_string($key) && trim($key) !== '') || is_int($key)) {
            return new ObjectKey((string) $key);
        }

        throw new InvalidArgument(sprintf('The batch record at position %d must include its "key".', $position));
    }

    /**
     * Query rows return related fields as flat keys such as "parent.id", while object
     * reads nest them. Expanding the keys lets one mapper handle both shapes.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function expandDottedKeys(array $row): array
    {
        $expanded = [];

        foreach ($row as $field => $value) {
            if (! str_contains($field, '.')) {
                $expanded[$field] = $value;
            }
        }

        foreach ($row as $field => $value) {
            if (str_contains($field, '.')) {
                $expanded = self::setPath($expanded, explode('.', $field), $value, $field);
            }
        }

        return $expanded;
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  list<string>  $segments
     * @return array<string, mixed>
     */
    private static function setPath(array $target, array $segments, mixed $value, string $field): array
    {
        $segment = array_shift($segments);

        if ($segment === null || $segment === '') {
            return [...$target, $field => $value];
        }

        if ($segments === []) {
            $target[$segment] ??= $value;

            return $target;
        }

        $child = $target[$segment] ?? [];

        if (! is_array($child) || ($child !== [] && array_is_list($child))) {
            return [...$target, $field => $value];
        }

        /** @var array<string, mixed> $child */
        $target[$segment] = self::setPath($child, $segments, $value, $field);

        return $target;
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
