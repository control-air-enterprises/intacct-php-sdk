<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Resources\Time\Timesheets\TimesheetsClient;
use ControlAir\Intacct\Resources\Time\TimeTypes\TimeTypesClient;

final readonly class Time
{
    public TimesheetsClient $timesheets;

    public TimeTypesClient $timeTypes;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->timesheets = new TimesheetsClient($transport, $queries);
        $this->timeTypes = new TimeTypesClient($transport, $queries);
    }
}
