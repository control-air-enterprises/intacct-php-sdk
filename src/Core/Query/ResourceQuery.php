<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Query;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;

final readonly class ResourceQuery
{
    /**
     * @param  list<Filter>  $filters
     * @param  list<OrderBy>  $orderBy
     * @param  list<string>  $fields  Fields selected beside the client's own, such as custom `nsp::` fields.
     */
    public function __construct(
        public array $filters = [],
        public string $filterExpression = 'and',
        public FilterParameters $filterParameters = new FilterParameters,
        public array $orderBy = [],
        public int $start = 1,
        public int $size = 100,
        public array $fields = [],
    ) {
        Assert::notBlank($this->filterExpression, 'The filter expression');

        foreach ($this->fields as $field) {
            Assert::notBlank($field, 'A query field');
        }

        if ($this->start < 1) {
            throw new InvalidArgument('Query start must be at least 1.');
        }

        if ($this->size < 1 || $this->size > 4000) {
            throw new InvalidArgument('Query size must be between 1 and 4000.');
        }
    }

    public function withStart(int $start): self
    {
        return new self(
            filters: $this->filters,
            filterExpression: $this->filterExpression,
            filterParameters: $this->filterParameters,
            orderBy: $this->orderBy,
            start: $start,
            size: $this->size,
            fields: $this->fields,
        );
    }

    public function withSize(int $size): self
    {
        return new self(
            filters: $this->filters,
            filterExpression: $this->filterExpression,
            filterParameters: $this->filterParameters,
            orderBy: $this->orderBy,
            start: $this->start,
            size: $size,
            fields: $this->fields,
        );
    }

    /**
     * Selects additional fields beside the ones the resource client maps, such as
     * `nsp::COLOR` for a custom field. The mapped resource exposes custom fields through
     * its `customFields` property.
     */
    public function withFields(string ...$fields): self
    {
        return new self(
            filters: $this->filters,
            filterExpression: $this->filterExpression,
            filterParameters: $this->filterParameters,
            orderBy: $this->orderBy,
            start: $this->start,
            size: $this->size,
            fields: array_values(array_unique([...$this->fields, ...array_values($fields)])),
        );
    }

    /**
     * Builds the query for a resource client, appending the additional fields to its own.
     *
     * @param  non-empty-list<string>  $fields
     */
    public function select(array $fields): Query
    {
        return new Query(
            fields: [...$fields, ...array_values(array_diff($this->fields, $fields))],
            filters: $this->filters,
            filterExpression: $this->filterExpression,
            filterParameters: $this->filterParameters,
            orderBy: $this->orderBy,
            start: $this->start,
            size: $this->size,
        );
    }
}
