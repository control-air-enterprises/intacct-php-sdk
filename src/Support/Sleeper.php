<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Support;

interface Sleeper
{
    public function sleep(int $milliseconds): void;
}
