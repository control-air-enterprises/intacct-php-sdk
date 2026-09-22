<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Response;

use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

/** The per-record outcome of a batch create, update or delete, in request order. */
final readonly class BatchResult implements \Countable
{
    /**
     * @param  list<BatchItemResult>  $items
     */
    public function __construct(
        public array $items,
        public ResponseMeta $meta,
        public int $statusCode = 200,
    ) {}

    /**
     * Maps a batch response, matching each result to the request record at the same position.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<ObjectKey|null>  $requestedKeys  one entry per request record; null when unknown (creates)
     * @param  bool  $emptyMeansSuccess  treat an empty body as success for every record (batch deletes)
     */
    public static function fromPayload(
        array $payload,
        int $statusCode,
        array $requestedKeys,
        bool $emptyMeansSuccess = false,
    ): self {
        $meta = ResponseMeta::fromArray(ArrayReader::object($payload['ia::meta'] ?? null) ?? []);
        $expected = count($requestedKeys);
        $result = $payload['ia::result'] ?? null;

        if ($payload === [] && ($statusCode === 204 || $emptyMeansSuccess)) {
            // A fully successful DELETE answers 204 with no body: every requested key succeeded.
            $items = [];

            foreach ($requestedKeys as $position => $key) {
                $items[] = new BatchItemResult($position, $statusCode, self::fallback($key));
            }

            return new self($items, $meta, $statusCode);
        }

        if (is_array($result) && ! array_is_list($result) && $expected === 1) {
            $result = [$result];
        }

        if (! is_array($result) || ! array_is_list($result)) {
            throw new MappingException('The batch response does not contain an ia::result list.');
        }

        if (count($result) !== $expected) {
            throw new MappingException(sprintf(
                'The batch response contains %d results for %d records, so results cannot be matched by position.',
                count($result),
                $expected,
            ));
        }

        $items = [];

        foreach ($result as $position => $item) {
            $item = ArrayReader::object($item)
                ?? throw new MappingException(sprintf('Batch result %d is not an object.', $position));

            $items[] = self::item($position, $item, $statusCode, $requestedKeys[$position]);
        }

        return new self($items, $meta, $statusCode);
    }

    public function isSuccessful(): bool
    {
        foreach ($this->items as $item) {
            if (! $item->isSuccessful()) {
                return false;
            }
        }

        return true;
    }

    /** @return list<BatchItemResult> */
    public function successes(): array
    {
        return array_values(array_filter($this->items, static fn (BatchItemResult $item): bool => $item->isSuccessful()));
    }

    /** @return list<BatchItemResult> */
    public function failures(): array
    {
        return array_values(array_filter($this->items, static fn (BatchItemResult $item): bool => ! $item->isSuccessful()));
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @param array<string, mixed> $item */
    private static function item(int $position, array $item, int $statusCode, ?ObjectKey $requestedKey): BatchItemResult
    {
        $error = ResultError::fromItem($item);
        $status = ArrayReader::int($item, 'ia::status') ?? match (true) {
            $error !== null => $statusCode >= 400 ? $statusCode : 400,
            $statusCode === 207 => 200,
            default => $statusCode,
        };
        $data = ArrayReader::object($item['ia::result'] ?? null) ?? $item;

        return new BatchItemResult(
            $position,
            $status,
            ObjectReference::fromArray($data) ?? self::fallback($requestedKey),
            $error,
            $item,
        );
    }

    private static function fallback(?ObjectKey $key): ?ObjectReference
    {
        return $key === null ? null : new ObjectReference($key, null);
    }
}
