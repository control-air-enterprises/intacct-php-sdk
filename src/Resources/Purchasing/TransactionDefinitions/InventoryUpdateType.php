<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\TransactionDefinitions;

enum InventoryUpdateType: string
{
    case None = 'no';
    case Quantity = 'quantity';
    case Value = 'value';
    case QuantityAndValue = 'quantityAndValue';
}
