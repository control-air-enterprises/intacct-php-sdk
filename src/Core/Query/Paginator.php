<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Query;

use Closure;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use Generator;
use IteratorAggregate;

/**
 * Lazily walks every page of a typed resource query.
 *
 * Pagination stops when the response has no `next` offset, when a page is
 * empty, when `next` does not advance past the current `start`, or when the
 * optional `maxPages` guard is reached.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
final readonly class Paginator implements IteratorAggregate
{
    /**
     * @param  Closure(ResourceQuery): Page<T>  $fetch
     */
    public function __construct(
        private Closure $fetch,
        private ResourceQuery $query = new ResourceQuery,
        private ?int $maxPages = null,
    ) {
        if ($this->maxPages !== null && $this->maxPages < 1) {
            throw new InvalidArgument('Paginator max pages must be at least 1.');
        }
    }

    /**
     * @template TItem
     *
     * @param  Closure(ResourceQuery): Page<TItem>  $fetch
     * @return self<TItem>
     */
    public static function over(
        Closure $fetch,
        ResourceQuery $query = new ResourceQuery,
        ?int $maxPages = null,
    ): self {
        return new self($fetch, $query, $maxPages);
    }

    /** @return self<T> */
    public function withMaxPages(?int $maxPages): self
    {
        return new self($this->fetch, $this->query, $maxPages);
    }

    /** @return Generator<int, Page<T>, mixed, void> */
    public function pages(): Generator
    {
        $query = $this->query;
        $fetched = 0;

        while (true) {
            $page = ($this->fetch)($query);
            $fetched++;

            yield $page;

            $next = $page->meta->next;

            if ($next === null || $page->items === [] || $next <= $query->start) {
                return;
            }

            if ($this->maxPages !== null && $fetched >= $this->maxPages) {
                return;
            }

            $query = $query->withStart($next);
        }
    }

    /** @return Generator<int, T, mixed, void> */
    public function items(): Generator
    {
        $index = 0;

        foreach ($this->pages() as $page) {
            foreach ($page->items as $item) {
                yield $index++ => $item;
            }
        }
    }

    /** @return Generator<int, T, mixed, void> */
    public function getIterator(): Generator
    {
        return $this->items();
    }
}
