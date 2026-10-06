<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectChangeOrders;

enum ProjectChangeOrderState: string
{
    case Draft = 'draft';
    case Posted = 'posted';
}
