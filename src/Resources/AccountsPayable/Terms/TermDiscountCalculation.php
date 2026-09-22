<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Terms;

/** The bill total a term discount is calculated on. */
enum TermDiscountCalculation: string
{
    case LineItemsTotal = 'lineItemsTotal';
    case BillTotal = 'billTotal';
}
