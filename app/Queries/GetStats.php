<?php

namespace App\Queries;

class GetStats
{
    public function __construct(
        public readonly string $userId
    ) {}
} 