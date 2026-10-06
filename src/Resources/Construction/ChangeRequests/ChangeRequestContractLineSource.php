<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ChangeRequests;

/** Where the project contract line of a change request's lines comes from. */
enum ChangeRequestContractLineSource: string
{
    case None = 'none';
    case ProjectChangeOrder = 'projectChangeOrder';
    case ChangeRequest = 'changeRequest';
    case ChangeRequestLine = 'changeRequestLine';
}
