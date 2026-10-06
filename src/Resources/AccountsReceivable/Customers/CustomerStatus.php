<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsReceivable\Customers;

enum CustomerStatus: string
{
    case Active = 'active';
    case ActiveNonPosting = 'activeNonPosting';
    case Inactive = 'inactive';
}
