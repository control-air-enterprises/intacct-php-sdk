<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContractLines;

enum ProjectContractLineBillingType: string
{
    case ProgressBill = 'progressBill';
    case TimeAndMaterial = 'timeAndMaterial';
}
