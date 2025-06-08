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

    public function countAttemptedFor(string $userId): int
    {
        return PracticeStatus::where('user_id', $userId)
            ->where('status', '!=', PracticeStatus::STATUS_NOT_ANSWERED)
            ->count();
    }

    public function resetFor(string $userId): int
    {
        return PracticeStatus::where('user_id', $userId)->update([
            'status' => PracticeStatus::STATUS_NOT_ANSWERED,
            'last_attempted_at' => null,
        ]);
    }

    public function findBy(int $flashcardId, string $userId): ?PracticeStatus
    {
        return PracticeStatus::where('flashcard_id', $flashcardId)
            ->where('user_id', $userId)
            ->first();
    }

    public function updateStatus(PracticeStatus $practiceStatus, string $newStatus, ?\DateTimeImmutable $lastAttemptedAt = null): PracticeStatus
    {
        $practiceStatus->update([
            'status' => $newStatus,
            'last_attempted_at' => $lastAttemptedAt,
        ]);

        return $practiceStatus;
    }

    public function countCorrectAttempts(string $userId): int
    {
        return PracticeStatus::where('user_id', $userId)
            ->where('status', PracticeStatus::STATUS_CORRECT)
            ->count();
    }
}
