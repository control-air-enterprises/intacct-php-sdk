<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContractLines;

/** Which price column of the contract line an entry contributes to. */
enum ProjectContractLineEntryWorkflowType: string
{
    case Original = 'original';
    case Revision = 'revision';
    case Forecast = 'forecast';
    case Other = 'other';
}
