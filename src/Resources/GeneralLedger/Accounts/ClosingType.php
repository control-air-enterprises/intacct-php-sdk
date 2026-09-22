<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\GeneralLedger\Accounts;

enum ClosingType: string
{
    case NonClosingAccount = 'nonClosingAccount';
    case ClosingAccount = 'closingAccount';
    case ClosedToAccount = 'closedToAccount';
}
