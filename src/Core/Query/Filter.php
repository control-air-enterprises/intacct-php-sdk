<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Query;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;

final readonly class Filter
{
    /**
     * @param  scalar|null|list<scalar|null>  $value
     */
    public function __construct(
        public string $field,
        public FilterOperator $operator,
        public string|int|float|bool|null|array $value,
    ) {
        Assert::notBlank($this->field, 'The filter field');

        $requiresList = in_array($this->operator, [
            FilterOperator::In,
            FilterOperator::NotIn,
            FilterOperator::Between,
            FilterOperator::NotBetween,
        ], true);

        if ($requiresList && (! is_array($this->value) || $this->value === [])) {
            throw new InvalidArgument(sprintf('%s filters require a non-empty list.', $this->operator->value));
        }

        if (! $requiresList && is_array($this->value)) {
            throw new InvalidArgument(sprintf('%s filters require a scalar value.', $this->operator->value));
        }

        if (in_array($this->operator, [FilterOperator::Between, FilterOperator::NotBetween], true)
            && is_array($this->value)
            && count($this->value) !== 2) {
            throw new InvalidArgument(sprintf('%s filters require exactly two values.', $this->operator->value));
        }
    }

    public static function equal(string $field, string|int|float|bool|null $value): self
    {
        return new self($field, FilterOperator::Equal, $value);
    }

    public static function notEqual(string $field, string|int|float|bool|null $value): self
    {
        return new self($field, FilterOperator::NotEqual, $value);
    }

    /** @param non-empty-list<scalar|null> $values */
    public static function in(string $field, array $values): self
    {
        return new self($field, FilterOperator::In, $values);
    }

    /** @param non-empty-list<scalar|null> $values */
    public static function notIn(string $field, array $values): self
    {
        return new self($field, FilterOperator::NotIn, $values);
    }

    /** @return array<string, array<string, scalar|null|list<scalar|null>>> */
    public function toArray(): array
    {
        return [
            $this->operator->value => [
                $this->field => $this->value,
            ],
        ];
    }
}
