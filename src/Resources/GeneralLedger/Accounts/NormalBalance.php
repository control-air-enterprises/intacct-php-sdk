<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\GeneralLedger\Accounts;

enum NormalBalance: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
