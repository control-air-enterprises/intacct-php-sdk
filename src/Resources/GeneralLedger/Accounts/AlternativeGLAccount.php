<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\GeneralLedger\Accounts;

enum AlternativeGLAccount: string
{
    case None = 'none';
    case PayablesAccount = 'payablesAccount';
    case ReceivablesAccount = 'receivablesAccount';
}
