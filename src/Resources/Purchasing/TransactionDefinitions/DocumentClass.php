<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\TransactionDefinitions;

enum DocumentClass: string
{
    case Quote = 'quote';
    case Order = 'order';
    case List = 'list';
    case Invoice = 'invoice';
    case Adjustment = 'adjustment';
    case Other = 'other';
}
