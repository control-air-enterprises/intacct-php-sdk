<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\GeneralLedger\Accounts;

enum ConstructionCategory: string
{
    case Cost = 'cost';
    case Revenue = 'revenue';
    case Overbilling = 'overbilling';
    case Underbilling = 'underbilling';
    case Offset = 'offset';
    case UnderbillingOffset = 'underbillingOffset';
    case OverbillingOffset = 'overbillingOffset';
}
