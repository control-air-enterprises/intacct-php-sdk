<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time\Timesheets;

/**
 * The approval state of one line. Sage sets it from the timesheet header; lines are never
 * written with a state.
 */
enum TimesheetLineState: string
{
    case Draft = 'draft';
    case Saved = 'saved';
    case ReadyForApproval = 'readyForApproval';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyApproved = 'partiallyApproved';
    case Declined = 'declined';
}
