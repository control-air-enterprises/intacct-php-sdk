<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Composite;

use ControlAir\Intacct\Exceptions\InvalidArgument;

/**
 * An immutable, ordered list of 2 to 10 sub-requests sent to /services/core/composite.
 *
 * Sub-requests run sequentially. Execution stops at the first failure and earlier
 * sub-requests are not rolled back; the remaining ones are reported as skipped.
 */
final readonly class CompositeRequest implements \Countable
{
    public const MIN_OPERATIONS = 2;

    public const MAX_OPERATIONS = 10;

    /** @var list<CompositeOperation> */
    public array $operations;

    /**
     * Accepts fewer than two operations so the request can be built up with with();
     * the minimum is enforced when the request is serialized.
     */
    public function __construct(CompositeOperation ...$operations)
    {
        $operations = array_values($operations);

        if (count($operations) > self::MAX_OPERATIONS) {
            throw new InvalidArgument(sprintf(
                'A composite request can contain at most %d sub-requests; %d given.',
                self::MAX_OPERATIONS,
                count($operations),
            ));
        }

        $references = [];

        foreach ($operations as $operation) {
            if ($operation->resultReference === null) {
                continue;
            }

            if (isset($references[$operation->resultReference])) {
                throw new InvalidArgument(sprintf(
                    'The result reference "%s" is used by more than one sub-request.',
                    $operation->resultReference,
                ));
            }

            $references[$operation->resultReference] = true;
        }

        $this->operations = $operations;
    }

    /** Creates a request and checks the 2 to 10 sub-request limit straight away. */
    public static function of(CompositeOperation ...$operations): self
    {
        $request = new self(...$operations);
        $request->assertExecutable();

        return $request;
    }

    public function with(CompositeOperation $operation): self
    {
        return new self(...[...$this->operations, $operation]);
    }

    public function assertExecutable(): void
    {
        if (count($this->operations) < self::MIN_OPERATIONS) {
            throw new InvalidArgument(sprintf(
                'A composite request must contain between %d and %d sub-requests; %d given.',
                self::MIN_OPERATIONS,
                self::MAX_OPERATIONS,
                count($this->operations),
            ));
        }
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        $this->assertExecutable();

        return array_map(static fn (CompositeOperation $operation): array => $operation->toArray(), $this->operations);
    }

    public function count(): int
    {
        return count($this->operations);
    }
}
