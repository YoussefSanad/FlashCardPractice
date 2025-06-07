<?php

namespace App\ValueObjects;

use App\Models\PracticeStatus;

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
        return match ($this->status) {
            PracticeStatus::STATUS_NOT_ANSWERED => 'Not answered',
            PracticeStatus::STATUS_CORRECT => 'Correct',
            PracticeStatus::STATUS_INCORRECT => 'Incorrect',
            default => 'Unknown',
        };
    }
}
