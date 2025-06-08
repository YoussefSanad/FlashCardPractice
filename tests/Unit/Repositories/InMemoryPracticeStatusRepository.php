<?php

namespace Tests\Unit\Repositories;

use App\Enums\Status;
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

    public function findBy(int $flashcardId, string $userId): ?PracticeStatus
    {
        foreach ($this->progressRecords as $progress) {
            if ($progress->flashcard_id === $flashcardId && $progress->user_id === $userId) {
                return $progress;
            }
        }
        return null;
    }

    public function countAttemptedFor(string $userId): int
    {
        $count = 0;
        foreach ($this->progressRecords as $progress) {
            if ($progress->user_id === $userId && $progress->status !== Status::NOT_ANSWERED->value) {
                $count++;
            }
        }
        return $count;
    }

    public function resetFor(string $userId): int
    {
        $updated = 0;
        foreach ($this->progressRecords as $progress) {
            if ($progress->user_id === $userId) {
                $progress->status = Status::NOT_ANSWERED->value;
                $progress->last_attempted_at = null;
                $updated++;
            }
        }
        return $updated;
    }

    public function updateStatus(PracticeStatus $practiceStatus, string $newStatus, ?\DateTimeImmutable $lastAttemptedAt = null): PracticeStatus
    {
        $practiceStatus->status = $newStatus;
        $practiceStatus->last_attempted_at = $lastAttemptedAt;

        return $practiceStatus;
    }

    public function countCorrectAttempts(string $userId): int
    {
        $count = 0;
        foreach ($this->progressRecords as $progress) {
            if ($progress->user_id === $userId && $progress->status === Status::CORRECT->value) {
                $count++;
            }
        }
        return $count;
    }
}
