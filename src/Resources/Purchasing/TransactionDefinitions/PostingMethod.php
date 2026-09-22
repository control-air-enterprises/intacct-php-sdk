<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\TransactionDefinitions;

enum PostingMethod: string
{
    case AccountsPayable = 'toAP';
    case GeneralLedger = 'toGL';
    case None = 'noPosting';
}
