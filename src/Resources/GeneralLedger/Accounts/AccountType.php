<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\GeneralLedger\Accounts;

enum AccountType: string
{
    case BalanceSheet = 'balanceSheet';
    case IncomeStatement = 'incomeStatement';
}
