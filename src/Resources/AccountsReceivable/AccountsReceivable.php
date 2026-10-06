<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsReceivable;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Resources\AccountsReceivable\Customers\CustomersClient;

final readonly class AccountsReceivable
{
    public CustomersClient $customers;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->customers = new CustomersClient($transport, $queries);
    }
}
