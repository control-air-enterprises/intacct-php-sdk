<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Resources\AccountsPayable\Terms\TermsClient;
use ControlAir\Intacct\Resources\AccountsPayable\Vendors\VendorsClient;

final readonly class AccountsPayable
{
    public VendorsClient $vendors;

    public TermsClient $terms;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->vendors = new VendorsClient($transport, $queries);
        $this->terms = new TermsClient($transport, $queries);
    }
}
