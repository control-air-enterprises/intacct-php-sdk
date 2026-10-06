<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContracts;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;

/** The read-only `billing` totals of a project contract, maintained by Sage as the contract is billed. */
final readonly class ProjectContractBilling
{
    public function __construct(
        public ?Decimal $billedPrice = null,
        public ?Decimal $totalBilledNetRetainage = null,
        public ?Decimal $percentBilled = null,
        public ?Decimal $percentBilledNetRetainage = null,
        public ?Decimal $totalRetainageHeld = null,
        public ?Decimal $totalRetainageReleased = null,
        public ?Decimal $retainageBalance = null,
        public ?Decimal $balanceToBill = null,
        public ?Decimal $balanceToBillNetRetainage = null,
        public ?Decimal $totalPaymentsReceived = null,
        public ?Decimal $netTotalBilled = null,
        public ?Decimal $netTotalPaymentsReceived = null,
        public ?string $lastApplicationNumber = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            billedPrice: ArrayReader::decimal($data, 'billedPrice'),
            totalBilledNetRetainage: ArrayReader::decimal($data, 'totalBilledNetRetainage'),
            percentBilled: ArrayReader::decimal($data, 'percentBilled'),
            percentBilledNetRetainage: ArrayReader::decimal($data, 'percentBilledNetRetainage'),
            totalRetainageHeld: ArrayReader::decimal($data, 'totalRetainageHeld'),
            totalRetainageReleased: ArrayReader::decimal($data, 'totalRetainageReleased'),
            retainageBalance: ArrayReader::decimal($data, 'retainageBalance'),
            balanceToBill: ArrayReader::decimal($data, 'balanceToBill'),
            balanceToBillNetRetainage: ArrayReader::decimal($data, 'balanceToBillNetRetainage'),
            totalPaymentsReceived: ArrayReader::decimal($data, 'totalPaymentsReceived'),
            netTotalBilled: ArrayReader::decimal($data, 'netTotalBilled'),
            netTotalPaymentsReceived: ArrayReader::decimal($data, 'netTotalPaymentsReceived'),
            lastApplicationNumber: ArrayReader::string($data, 'lastApplicationNumber'),
        );
    }
}
