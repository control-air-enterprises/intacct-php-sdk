<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Support;

final readonly class SystemSleeper implements Sleeper
{
    public function sleep(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }

        // Some platforms reject usleep() values of one second or more, so sleep in sub-second chunks.
        $remaining = $milliseconds * 1000;

        while ($remaining > 0) {
            $step = min($remaining, 999_999);
            usleep($step);
            $remaining -= $step;
        }
    }
}
