<?php

namespace App\Queries;

class GetPracticeableQuestions
{
    public function __construct(
        public readonly string $userId
    ) {}
} 