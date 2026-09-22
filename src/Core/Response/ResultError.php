<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Response;

use ControlAir\Intacct\Support\ArrayReader;

/**
 * An error reported for one item of a batch or composite response.
 *
 * Sage Intacct sends ia::error either as a single object or as a list of objects. The
 * named properties describe the first error; $errors keeps every error as received.
 */
final readonly class ResultError
{
    /** Codes Sage Intacct uses for sub-requests skipped after an earlier failure. */
    public const SKIPPED_CODES = ['unprocessed', 'atomicOperationFailure'];

    /**
     * @param  array<string, mixed>  $additionalInfo
     * @param  list<array<string, mixed>>  $details
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        public ?string $code,
        public ?string $message,
        public ?string $errorId = null,
        public ?string $supportId = null,
        public ?string $target = null,
        public array $additionalInfo = [],
        public array $details = [],
        public array $errors = [],
    ) {}

    /**
     * Reads ia::error from a result item, or from the item's nested ia::result.
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromItem(array $item): ?self
    {
        $error = self::fromValue($item['ia::error'] ?? null);

        if ($error !== null) {
            return $error;
        }

        $result = ArrayReader::object($item['ia::result'] ?? null);

        return $result === null ? null : self::fromValue($result['ia::error'] ?? null);
    }

    /** Maps an ia::error value that is either an error object or a list of them. */
    public static function fromValue(mixed $value): ?self
    {
        if (! is_array($value)) {
            return null;
        }

        $errors = [];

        foreach (array_is_list($value) ? $value : [$value] as $error) {
            $error = ArrayReader::object($error);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        if ($errors === []) {
            return null;
        }

        $first = $errors[0];

        return new self(
            code: ArrayReader::string($first, 'code'),
            message: ArrayReader::string($first, 'message'),
            errorId: ArrayReader::string($first, 'errorId'),
            supportId: ArrayReader::string($first, 'supportId'),
            target: ArrayReader::string($first, 'target'),
            additionalInfo: ArrayReader::object($first['additionalInfo'] ?? null) ?? [],
            details: ArrayReader::list($first, 'details'),
            errors: $errors,
        );
    }

    public function isSkipped(): bool
    {
        return in_array($this->code, self::SKIPPED_CODES, true);
    }
}
