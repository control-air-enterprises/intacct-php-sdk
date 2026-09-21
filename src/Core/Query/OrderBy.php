<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Query;

use ControlAir\Intacct\Support\Assert;

final readonly class OrderBy
{
    public function __construct(
        public string $field,
        public SortDirection $direction = SortDirection::Ascending,
    ) {
        Assert::notBlank($this->field, 'The order-by field');
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [$this->field => $this->direction->value];
    }
}
