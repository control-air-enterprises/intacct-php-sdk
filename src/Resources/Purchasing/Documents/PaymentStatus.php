<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\Documents;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case PartiallyPaid = 'partiallyPaid';
    case Selected = 'selected';
    case Open = 'open';
}
