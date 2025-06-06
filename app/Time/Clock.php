<?php

namespace App\Time;

use DateTimeImmutable;

interface Clock
{
    /**
     * Get the current date and time.
     */
    public function now(): DateTimeImmutable;
} 