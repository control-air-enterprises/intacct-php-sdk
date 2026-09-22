<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Vendors;

enum VendorStatus: string
{
    case Active = 'active';
    case ActiveNonPosting = 'activeNonPosting';
    case Inactive = 'inactive';
}
