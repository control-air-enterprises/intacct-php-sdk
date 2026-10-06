<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ChangeRequests;

enum ChangeRequestState: string
{
    case Draft = 'draft';
    case Posted = 'posted';
}
