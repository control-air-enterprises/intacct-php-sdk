<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Query;

enum SortDirection: string
{
    case Ascending = 'asc';
    case Descending = 'desc';
}
