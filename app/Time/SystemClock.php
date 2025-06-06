<?php

namespace App\Time;

use DateTimeImmutable;

class SystemClock implements Clock
{
    /**
     * Get the current system date and time.
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
} 