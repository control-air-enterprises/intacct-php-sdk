<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Composite;

use ControlAir\Intacct\Core\Response\ResponseMeta;
use ControlAir\Intacct\Core\Response\ResultError;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\ObjectReference;

/** The outcome of one composite sub-request; $position matches the request order (zero-based). */
final readonly class CompositeItemResult
{
    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $result  an object (reads, writes) or a list (queries)
     * @param  array<string, string>  $headers  the sub-request's ia::headers
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public int $position,
        public int $status,
        public ?array $result,
        public ?ResponseMeta $meta = null,
        public ?ResultError $error = null,
        public array $headers = [],
        public array $raw = [],
    ) {}

    /** @param array<string, mixed> $item */
    public static function fromArray(int $position, array $item): self
    {
        $status = ArrayReader::int($item, 'ia::status')
            ?? throw new MappingException(sprintf('Composite result %d does not contain an ia::status.', $position));
        $meta = ArrayReader::object($item['ia::meta'] ?? null);

        return new self(
            position: $position,
            status: $status,
            result: self::result($position, $item['ia::result'] ?? null),
            meta: $meta === null ? null : ResponseMeta::fromArray($meta),
            error: ResultError::fromItem($item),
            headers: self::headers($item['ia::headers'] ?? null),
            raw: $item,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300 && $this->error === null;
    }

    /** Whether the sub-request was skipped because an earlier one failed. */
    public function wasSkipped(): bool
    {
        return $this->status === 422 && $this->error !== null && $this->error->isSkipped();
    }

    public function isList(): bool
    {
        return $this->result !== null && $this->result !== [] && array_is_list($this->result);
    }

    /** @return array<string, mixed>|null the result when it is a single object */
    public function object(): ?array
    {
        if ($this->result === null || $this->isList()) {
            return null;
        }

        return ArrayReader::object($this->result) ?? [];
    }

    /** @return list<array<string, mixed>> the result rows; a single object is returned as one row */
    public function rows(): array
    {
        if ($this->result === null || $this->result === []) {
            return [];
        }

        $rows = [];

        foreach ($this->isList() ? $this->result : [$this->result] as $row) {
            // Empty JSON objects decode to [], which ArrayReader does not treat as an object.
            $rows[] = ArrayReader::object($row) ?? [];
        }

        return $rows;
    }

    /** The key/ID of the record a write returned, or the first row's for a list result. */
    public function reference(): ?ObjectReference
    {
        $row = $this->rows()[0] ?? null;

        return $row === null ? null : ObjectReference::fromArray($row);
    }

    /** @return array<string, mixed>|list<array<string, mixed>>|null */
    private static function result(int $position, mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        if (! array_is_list($value)) {
            $object = ArrayReader::object($value)
                ?? throw new MappingException(sprintf('Composite result %d has an invalid ia::result.', $position));

            // An error nested as ia::result.ia::error is exposed through $error instead.
            return array_key_exists('ia::error', $object) && count($object) === 1 ? null : $object;
        }

        $rows = [];

        foreach ($value as $row) {
            // An empty JSON object decodes to [], which is not a string-keyed array.
            $object = $row === [] ? [] : ArrayReader::object($row);

            if ($object === null) {
                throw new MappingException(sprintf('Composite result %d contains a row that is not an object.', $position));
            }

            $rows[] = $object;
        }

        return $rows;
    }

    /** @return array<string, string> */
    private static function headers(mixed $value): array
    {
        $headers = [];

        foreach (ArrayReader::object($value) ?? [] as $name => $header) {
            if (is_scalar($header)) {
                $headers[$name] = (string) $header;
            }
        }

        return $headers;
    }
}
