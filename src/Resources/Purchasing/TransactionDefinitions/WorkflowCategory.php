<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\TransactionDefinitions;

enum WorkflowCategory: string
{
    case Quote = 'quote';
    case Order = 'order';
    case Shipping = 'shipping';
    case Invoice = 'invoice';
    case Return = 'return';
}
