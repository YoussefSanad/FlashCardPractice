<?php

namespace Tests\Unit\Time;

use App\Time\Clock;
use DateTimeImmutable;

class FakeClock implements Clock
{
    private DateTimeImmutable $currentTime;

    public function __construct(?DateTimeImmutable $currentTime = null)
    {
        $this->currentTime = $currentTime ?? new DateTimeImmutable();
    }

    /**
     * Get the current fake time.
     */
    public function now(): DateTimeImmutable
    {
        return $this->currentTime;
    }

    /**
     * Set the fake time.
     */
    public function setTime(DateTimeImmutable $time): void
    {
        $this->currentTime = $time;
    }
} 