<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ChangeRequests;

/** The budget bucket a change request status or line posts to. */
enum ChangeRequestWorkflowType: string
{
    case None = 'none';
    case Original = 'original';
    case Revision = 'revision';
    case Forecast = 'forecast';
    case ApprovedChange = 'approvedChange';
    case PendingChange = 'pendingChange';
    case Other = 'other';
}
