<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\Items;

enum ItemType: string
{
    case Inventory = 'inventory';
    case NonInventory = 'nonInventory';
    case PurchaseOnlyNonInventory = 'purchaseOnlyNonInventory';
    case SalesOnlyNonInventory = 'salesOnlyNonInventory';
    case Kit = 'kit';
    case StockableKit = 'stockableKit';
}
