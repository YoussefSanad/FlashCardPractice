<?php

namespace App\Commands;

class SubmitAnswer implements RequiresTransaction
{
    public function __construct(
        public readonly int $flashcardId,
        public readonly string $userAnswer,
        public readonly string $userId
    ) {}
}
