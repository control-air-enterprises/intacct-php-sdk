<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContractLines;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;

/** The read-only `billing` totals of a contract line, maintained by Sage as the line is billed. */
final readonly class ProjectContractLineBilling
{
    public function __construct(
        public ?Decimal $billedPrice = null,
        public ?Decimal $billedNetRetainage = null,
        public ?Decimal $percentBilled = null,
        public ?Decimal $percentBilledNetRetainage = null,
        public ?Decimal $previouslyAppliedPrice = null,
        public ?Decimal $retainageHeld = null,
        public ?Decimal $retainageReleased = null,
        public ?Decimal $retainageBalance = null,
        public ?Decimal $paymentsReceived = null,
        public ?string $externalReferenceNumber = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            billedPrice: ArrayReader::decimal($data, 'billedPrice'),
            billedNetRetainage: ArrayReader::decimal($data, 'billedNetRetainage'),
            percentBilled: ArrayReader::decimal($data, 'percentBilled'),
            percentBilledNetRetainage: ArrayReader::decimal($data, 'percentBilledNetRetainage'),
            previouslyAppliedPrice: ArrayReader::decimal($data, 'previouslyAppliedPrice'),
            retainageHeld: ArrayReader::decimal($data, 'retainageHeld'),
            retainageReleased: ArrayReader::decimal($data, 'retainageReleased'),
            retainageBalance: ArrayReader::decimal($data, 'retainageBalance'),
            paymentsReceived: ArrayReader::decimal($data, 'paymentsReceived'),
            externalReferenceNumber: ArrayReader::string($data, 'externalReferenceNumber'),
        );
    }
}
