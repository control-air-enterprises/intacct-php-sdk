<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Response;

/** @template T */
final readonly class Page
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public array $items,
        public ResponseMeta $meta,
    ) {}

    public function hasNextPage(): bool
    {
        return $this->meta->next !== null;
    }
}
