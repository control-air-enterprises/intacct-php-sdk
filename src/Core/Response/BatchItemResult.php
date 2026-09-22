<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Response;

use ControlAir\Intacct\ValueObjects\ObjectReference;

/**
 * The outcome of one record in a batch create, update or delete.
 *
 * $position is the zero-based index of the record in the request. Sage Intacct omits the
 * key on some results (for example successful items of a partially failed DELETE), so
 * results are matched to requests by position. When the response omits the key, updates
 * and deletes fall back to the key that was sent.
 */
final readonly class BatchItemResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public int $position,
        public int $status,
        public ?ObjectReference $reference,
        public ?ResultError $error = null,
        public array $raw = [],
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300 && $this->error === null;
    }

    /** Whether Sage Intacct skipped this record because another record in an atomic batch failed. */
    public function wasSkipped(): bool
    {
        return $this->status === 422 && $this->error !== null && $this->error->isSkipped();
    }
}
