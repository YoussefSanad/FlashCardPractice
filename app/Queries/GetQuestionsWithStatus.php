<?php

namespace App\Queries;

class GetQuestionsWithStatus
{
    public function __construct(
        public readonly string $userId
    ) {}
}
