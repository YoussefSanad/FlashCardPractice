<?php

namespace App\Repositories;

use App\Models\PracticeStatus;

class EloquentPracticeStatusRepository implements PracticeStatusRepository
{
    public function create(int $flashcardId, string $userId, string $status, ?\DateTimeImmutable $lastAttemptedAt = null): PracticeStatus
    {
        return PracticeStatus::create([
            'flashcard_id' => $flashcardId,
            'user_id' => $userId,
            'status' => $status,
            'last_attempted_at' => $lastAttemptedAt,
        ]);
    }

    public function countNonNotAnsweredByUserId(string $userId): int
    {
        return PracticeStatus::where('user_id', $userId)
            ->where('status', '!=', PracticeStatus::STATUS_NOT_ANSWERED)
            ->count();
    }

    public function resetProgressByUserId(string $userId): int
    {
        return PracticeStatus::where('user_id', $userId)->update([
            'status' => PracticeStatus::STATUS_NOT_ANSWERED,
            'last_attempted_at' => null,
        ]);
    }

    public function findByFlashcardAndUser(int $flashcardId, string $userId): ?PracticeStatus
    {
        return PracticeStatus::where('flashcard_id', $flashcardId)
            ->where('user_id', $userId)
            ->first();
    }

    public function updateProgress(PracticeStatus $progress, string $status, ?\DateTimeImmutable $lastAttemptedAt = null): PracticeStatus
    {
        $progress->update([
            'status' => $status,
            'last_attempted_at' => $lastAttemptedAt,
        ]);

        return $progress;
    }

    public function countAllAttempts(string $userId): int
    {
        return PracticeStatus::where('user_id', $userId)
            ->where('status', '!=', PracticeStatus::STATUS_NOT_ANSWERED)
            ->count();
    }

    public function countCorrectAttempts(string $userId): int
    {
        return PracticeStatus::where('user_id', $userId)
            ->where('status', PracticeStatus::STATUS_CORRECT)
            ->count();
    }
}
