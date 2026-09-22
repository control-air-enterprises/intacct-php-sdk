<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\GeneralLedger;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Resources\GeneralLedger\Accounts\AccountsClient;

final readonly class GeneralLedger
{
    public AccountsClient $accounts;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->accounts = new AccountsClient($transport, $queries);
    }
}
