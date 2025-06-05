<?php

namespace App\Repositories;

use App\Models\QuestionProgress;

class EloquentQuestionProgressRepository implements QuestionProgressRepository
{
    public function create(int $flashcardId, string $userId, string $status, ?\DateTime $lastAttemptedAt = null): QuestionProgress
    {
        return QuestionProgress::create([
            'flashcard_id' => $flashcardId,
            'user_id' => $userId,
            'status' => $status,
            'last_attempted_at' => $lastAttemptedAt,
        ]);
    }

    public function countNonNotAnsweredByUserId(string $userId): int
    {
        return QuestionProgress::where('user_id', $userId)
            ->where('status', '!=', QuestionProgress::STATUS_NOT_ANSWERED)
            ->count();
    }

    public function resetProgressByUserId(string $userId): int
    {
        return QuestionProgress::where('user_id', $userId)->update([
            'status' => QuestionProgress::STATUS_NOT_ANSWERED,
            'last_attempted_at' => null,
        ]);
    }

    public function findByFlashcardAndUser(int $flashcardId, string $userId): ?QuestionProgress
    {
        return QuestionProgress::where('flashcard_id', $flashcardId)
            ->where('user_id', $userId)
            ->first();
    }

    public function updateProgress(QuestionProgress $progress, string $status, ?\DateTime $lastAttemptedAt = null): QuestionProgress
    {
        $progress->update([
            'status' => $status,
            'last_attempted_at' => $lastAttemptedAt,
        ]);

        return $progress;
    }
} 