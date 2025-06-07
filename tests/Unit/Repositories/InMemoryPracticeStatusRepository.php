<?php

namespace Tests\Unit\Repositories;

use App\Models\PracticeStatus;
use App\Repositories\PracticeStatusRepository;

class InMemoryPracticeStatusRepository implements PracticeStatusRepository
{
    private array $progressRecords = [];
    private int $nextId = 1;

    public function create(int $flashcardId, string $userId, string $status, ?\DateTimeImmutable $lastAttemptedAt = null): PracticeStatus
    {
        $progress = new PracticeStatus([
            'flashcard_id' => $flashcardId,
            'user_id' => $userId,
            'status' => $status,
            'last_attempted_at' => $lastAttemptedAt,
        ]);

        // Manually set the ID since we're not using database
        $progress->setAttribute('id', $this->nextId++);

        $this->progressRecords[] = $progress;

        return $progress;
    }

    public function getAll(): array
    {
        return $this->progressRecords;
    }

    public function findByFlashcardAndUser(int $flashcardId, string $userId): ?PracticeStatus
    {
        foreach ($this->progressRecords as $progress) {
            if ($progress->flashcard_id === $flashcardId && $progress->user_id === $userId) {
                return $progress;
            }
        }
        return null;
    }

    public function countNonNotAnsweredByUserId(string $userId): int
    {
        $count = 0;
        foreach ($this->progressRecords as $progress) {
            if ($progress->user_id === $userId && $progress->status !== PracticeStatus::STATUS_NOT_ANSWERED) {
                $count++;
            }
        }
        return $count;
    }

    public function resetProgressByUserId(string $userId): int
    {
        $updated = 0;
        foreach ($this->progressRecords as $progress) {
            if ($progress->user_id === $userId) {
                $progress->status = PracticeStatus::STATUS_NOT_ANSWERED;
                $progress->last_attempted_at = null;
                $updated++;
            }
        }
        return $updated;
    }

    public function updateProgress(PracticeStatus $progress, string $status, ?\DateTimeImmutable $lastAttemptedAt = null): PracticeStatus
    {
        $progress->status = $status;
        $progress->last_attempted_at = $lastAttemptedAt;

        return $progress;
    }

    public function countAllAttempts(string $userId): int
    {
        $count = 0;
        foreach ($this->progressRecords as $progress) {
            if ($progress->user_id === $userId && $progress->status !== PracticeStatus::STATUS_NOT_ANSWERED) {
                $count++;
            }
        }
        return $count;
    }

    public function countCorrectAttempts(string $userId): int
    {
        $count = 0;
        foreach ($this->progressRecords as $progress) {
            if ($progress->user_id === $userId && $progress->status === PracticeStatus::STATUS_CORRECT) {
                $count++;
            }
        }
        return $count;
    }
}
