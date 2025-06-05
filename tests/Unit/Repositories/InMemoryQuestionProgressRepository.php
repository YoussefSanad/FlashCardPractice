<?php

namespace Tests\Unit\Repositories;

use App\Models\QuestionProgress;
use App\Repositories\QuestionProgressRepository;

class InMemoryQuestionProgressRepository implements QuestionProgressRepository
{
    private array $progressRecords = [];
    private int $nextId = 1;

    public function create(int $flashcardId, string $userId, string $status, ?\DateTime $lastAttemptedAt = null): QuestionProgress
    {
        $progress = new QuestionProgress([
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

    public function findByFlashcardAndUser(int $flashcardId, string $userId): ?QuestionProgress
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
            if ($progress->user_id === $userId && $progress->status !== QuestionProgress::STATUS_NOT_ANSWERED) {
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
                $progress->status = QuestionProgress::STATUS_NOT_ANSWERED;
                $progress->last_attempted_at = null;
                $updated++;
            }
        }
        return $updated;
    }

    public function updateProgress(QuestionProgress $progress, string $status, ?\DateTime $lastAttemptedAt = null): QuestionProgress
    {
        $progress->status = $status;
        $progress->last_attempted_at = $lastAttemptedAt;
        
        return $progress;
    }
}
