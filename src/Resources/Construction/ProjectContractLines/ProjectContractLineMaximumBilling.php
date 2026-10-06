<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContractLines;

/** What caps billing on a contract line; `SpecifiedAmount` requires a maximum billing amount. */
enum ProjectContractLineMaximumBilling: string
{
    case TotalPrice = 'totalPrice';
    case SpecifiedAmount = 'specifiedAmount';
    case NoMaximum = 'noMaximum';
}
