<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Composite;

use ControlAir\Intacct\Core\Response\ResponseMeta;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;

/**
 * The response to a composite request: one item per sub-request, in request order, plus the
 * aggregate ia::meta. HTTP 207 signals that at least one sub-request failed or was skipped.
 */
final readonly class CompositeResult implements \Countable
{
    /** @param list<CompositeItemResult> $items */
    public function __construct(
        public array $items,
        public ResponseMeta $meta,
        public int $statusCode = 200,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload, int $statusCode = 200): self
    {
        $result = $payload['ia::result'] ?? null;

        if (! is_array($result) || ! array_is_list($result)) {
            throw new MappingException('The composite response does not contain an ia::result list.');
        }

        $items = [];

        foreach ($result as $position => $item) {
            $item = ArrayReader::object($item)
                ?? throw new MappingException(sprintf('Composite result %d is not an object.', $position));

            $items[] = CompositeItemResult::fromArray($position, $item);
        }

        return new self(
            $items,
            ResponseMeta::fromArray(ArrayReader::object($payload['ia::meta'] ?? null) ?? []),
            $statusCode,
        );
    }

    /** Whether every sub-request succeeded. */
    public function isSuccessful(): bool
    {
        return $this->firstFailure() === null;
    }

    public function item(int $position): CompositeItemResult
    {
        return $this->items[$position]
            ?? throw new InvalidArgument(sprintf('The composite result has no item at position %d.', $position));
    }

    /** The sub-request that stopped execution, if any. Skipped sub-requests are not failures of their own. */
    public function firstFailure(): ?CompositeItemResult
    {
        foreach ($this->items as $item) {
            if (! $item->isSuccessful() && ! $item->wasSkipped()) {
                return $item;
            }
        }

        foreach ($this->items as $item) {
            if (! $item->isSuccessful()) {
                return $item;
            }
        }

        return null;
    }

    /** @return list<CompositeItemResult> */
    public function skipped(): array
    {
        return array_values(array_filter($this->items, static fn (CompositeItemResult $item): bool => $item->wasSkipped()));
    }

    public function count(): int
    {
        return count($this->items);
    }
}
