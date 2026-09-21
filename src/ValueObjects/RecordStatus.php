<?php

declare(strict_types=1);

namespace ControlAir\Intacct\ValueObjects;

enum RecordStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
