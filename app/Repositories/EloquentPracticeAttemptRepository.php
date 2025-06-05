<?php

namespace App\Repositories;

use App\Models\PracticeAttempt;

class EloquentPracticeAttemptRepository implements PracticeAttemptRepository
{
    public function countByUserId(string $userId): int
    {
        return PracticeAttempt::where('user_id', $userId)->count();
    }

    public function deleteByUserId(string $userId): int
    {
        return PracticeAttempt::where('user_id', $userId)->delete();
    }

    public function create(int $flashcardId, string $userId, string $userAnswer, bool $isCorrect): PracticeAttempt
    {
        return PracticeAttempt::create([
            'flashcard_id' => $flashcardId,
            'user_id' => $userId,
            'user_answer' => $userAnswer,
            'is_correct' => $isCorrect,
        ]);
    }
} 