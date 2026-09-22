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
     */
    public function __construct(
        public array $filters = [],
        public string $filterExpression = 'and',
        public FilterParameters $filterParameters = new FilterParameters,
        public array $orderBy = [],
        public int $start = 1,
        public int $size = 100,
    ) {
        Assert::notBlank($this->filterExpression, 'The filter expression');

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
        );
    }

    /** @param non-empty-list<string> $fields */
    public function select(array $fields): Query
    {
        return new Query(
            fields: $fields,
            filters: $this->filters,
            filterExpression: $this->filterExpression,
            filterParameters: $this->filterParameters,
            orderBy: $this->orderBy,
            start: $this->start,
            size: $this->size,
        );
    }
}
