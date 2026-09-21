<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Response;

final readonly class ResponseMeta
{
    public function __construct(
        public int $totalCount,
        public ?int $start = null,
        public ?int $pageSize = null,
        public ?int $next = null,
        public ?int $previous = null,
        public ?int $totalSuccess = null,
        public ?int $totalError = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            totalCount: self::integer($data['totalCount'] ?? 0) ?? 0,
            start: self::integer($data['start'] ?? null),
            pageSize: self::integer($data['pageSize'] ?? null),
            next: self::integer($data['next'] ?? null),
            previous: self::integer($data['previous'] ?? null),
            totalSuccess: self::integer($data['totalSuccess'] ?? null),
            totalError: self::integer($data['totalError'] ?? null),
        );
    }

    private static function integer(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
