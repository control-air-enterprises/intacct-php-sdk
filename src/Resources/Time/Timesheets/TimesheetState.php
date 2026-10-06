<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time\Timesheets;

enum TimesheetState: string
{
    case Draft = 'draft';
    case Saved = 'saved';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyApproved = 'partiallyApproved';
    case Declined = 'declined';
    case PartiallyDeclined = 'partiallyDeclined';
}
