<?php

namespace Tests\Unit\Repositories;

use App\Models\PracticeAttempt;
use App\Repositories\PracticeAttemptRepository;

class InMemoryPracticeAttemptRepository implements PracticeAttemptRepository
{
    private array $practiceAttempts = [];
    private int $nextId = 1;

    public function create(int $flashcardId, string $userId, string $userAnswer, bool $isCorrect): PracticeAttempt
    {
        $attempt = new PracticeAttempt([
            'flashcard_id' => $flashcardId,
            'user_id' => $userId,
            'user_answer' => $userAnswer,
            'is_correct' => $isCorrect,
        ]);

        // Manually set the ID since we're not using database
        $attempt->setAttribute('id', $this->nextId++);

        $this->practiceAttempts[] = $attempt;

        return $attempt;
    }

    public function countByUserId(string $userId): int
    {
        $count = 0;
        foreach ($this->practiceAttempts as $attempt) {
            if ($attempt->user_id === $userId) {
                $count++;
            }
        }
        return $count;
    }

    public function deleteByUserId(string $userId): int
    {
        $originalCount = count($this->practiceAttempts);
        $this->practiceAttempts = array_filter($this->practiceAttempts, function ($attempt) use ($userId) {
            return $attempt->user_id !== $userId;
        });
        return $originalCount - count($this->practiceAttempts);
    }

    public function getAll(): array
    {
        return $this->practiceAttempts;
    }
} 