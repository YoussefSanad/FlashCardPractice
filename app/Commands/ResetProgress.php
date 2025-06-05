<?php

namespace App\Commands;

class ResetProgress
{
    public function __construct(
        public readonly string $userId
    ) {}
} 