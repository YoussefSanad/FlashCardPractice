<?php

namespace App\ValueObjects;

use App\Enums\Status;

class QuestionWithStatus
{
    public function __construct(
        public readonly int $flashcardId,
        public readonly string $userId,
        public readonly string $question,
        public readonly string $status,
    ) {}

    public function status(): string
    {
        return Status::from($this->status)->label();
    }
}
