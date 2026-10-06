<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContracts;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;

/**
 * The `summary` price totals of a project contract or contract line. Sage derives them
 * from the line entries and change orders, so the SDK only reads them.
 */
final readonly class ProjectContractSummary
{
    public function __construct(
        public ?Decimal $originalPrice = null,
        public ?Decimal $revisionPrice = null,
        public ?Decimal $approvedChangePrice = null,
        public ?Decimal $pendingChangePrice = null,
        public ?Decimal $otherPrice = null,
        public ?Decimal $totalPrice = null,
        public ?Decimal $forecastPrice = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            originalPrice: ArrayReader::decimal($data, 'originalPrice'),
            revisionPrice: ArrayReader::decimal($data, 'revisionPrice'),
            approvedChangePrice: ArrayReader::decimal($data, 'approvedChangePrice'),
            pendingChangePrice: ArrayReader::decimal($data, 'pendingChangePrice'),
            otherPrice: ArrayReader::decimal($data, 'otherPrice'),
            totalPrice: ArrayReader::decimal($data, 'totalPrice'),
            forecastPrice: ArrayReader::decimal($data, 'forecastPrice'),
        );
    }
}
