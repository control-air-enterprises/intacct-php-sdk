<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Support;

use ControlAir\Intacct\Support\Sleeper;

final class RecordingSleeper implements Sleeper
{
    /** @var list<int> */
    public array $delays = [];

    public function sleep(int $milliseconds): void
    {
        $this->delays[] = $milliseconds;
    }
}
