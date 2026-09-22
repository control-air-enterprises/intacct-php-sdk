<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Terms;

enum TermAmountUnit: string
{
    case Amount = 'amount';
    case Percentage = 'percentage';
}
