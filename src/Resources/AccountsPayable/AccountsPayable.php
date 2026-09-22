<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Resources\AccountsPayable\Vendors\VendorsClient;

final readonly class AccountsPayable
{
    public VendorsClient $vendors;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->vendors = new VendorsClient($transport, $queries);
    }
}
