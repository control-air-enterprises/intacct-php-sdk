<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Terms;

enum TermPenaltyCycle: string
{
    case NoPenalty = 'noPenalty';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Bimonthly = 'bimonthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'halfYearly';
    case Annually = 'annually';
}
