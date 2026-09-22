<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Model;

use ControlAir\Intacct\Core\Response\ResponseMeta;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class AllowedOperationsResult
{
    /** @param list<AllowedOperations> $records */
    public function __construct(
        public array $records,
        public ResponseMeta $meta,
    ) {}

    public function for(ObjectKey|string $key): ?AllowedOperations
    {
        $key = (string) $key;

        foreach ($this->records as $record) {
            if ($record->key === $key) {
                return $record;
            }
        }

        return null;
    }

    /** Records missing from the response are treated as not allowed. */
    public function allows(ObjectKey|string $key, string $operation): bool
    {
        return $this->for($key)?->allows($operation) ?? false;
    }
}
