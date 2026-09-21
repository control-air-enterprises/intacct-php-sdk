<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Query;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;

final readonly class Query
{
    /**
     * @param  non-empty-list<string>  $fields
     * @param  list<Filter>  $filters
     * @param  list<OrderBy>  $orderBy
     */
    public function __construct(
        public array $fields,
        public array $filters = [],
        public string $filterExpression = 'and',
        public FilterParameters $filterParameters = new FilterParameters,
        public array $orderBy = [],
        public int $start = 1,
        public int $size = 100,
    ) {
        foreach ($this->fields as $field) {
            Assert::notBlank($field, 'A query field');
        }

        if (count(array_unique($this->fields)) !== count($this->fields)) {
            throw new InvalidArgument('Query fields must be unique.');
        }

        Assert::notBlank($this->filterExpression, 'The filter expression');

        if ($this->start < 1) {
            throw new InvalidArgument('Query start must be at least 1.');
        }

        if ($this->size < 1 || $this->size > 4000) {
            throw new InvalidArgument('Query size must be between 1 and 4000.');
        }
    }

    /** @return array<string, mixed> */
    public function forObject(string $object): array
    {
        Assert::notBlank($object, 'The query object');

        $payload = [
            'object' => $object,
            'fields' => $this->fields,
            'start' => $this->start,
            'size' => $this->size,
        ];

        if ($this->filters !== []) {
            $payload['filters'] = array_map(
                static fn (Filter $filter): array => $filter->toArray(),
                $this->filters,
            );
            $payload['filterExpression'] = $this->filterExpression;
            $payload['filterParameters'] = $this->filterParameters->toArray();
        }

        if ($this->orderBy !== []) {
            $payload['orderBy'] = array_map(
                static fn (OrderBy $order): array => $order->toArray(),
                $this->orderBy,
            );
        }

        return $payload;
    }
}
