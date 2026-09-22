<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\Items;

enum CostMethod: string
{
    case Standard = 'standard';
    case Average = 'average';
    case Fifo = 'FIFO';
    case Lifo = 'LIFO';
}
