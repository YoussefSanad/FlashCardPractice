<?php

namespace App\Queries;

class GetPracticeProgress
{
    public function __construct(
        public readonly string $userId
    ) {}
} 