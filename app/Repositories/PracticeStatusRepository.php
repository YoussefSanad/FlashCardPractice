<?php

namespace App\Repositories;

use App\Models\PracticeStatus;

interface PracticeStatusRepository
{
    public function create(int $flashcardId, string $userId, string $status, ?\DateTimeImmutable $lastAttemptedAt = null): PracticeStatus;

    public function countNonNotAnsweredByUserId(string $userId): int;

    public function resetProgressByUserId(string $userId): int;

    public function findByFlashcardAndUser(int $flashcardId, string $userId): ?PracticeStatus;

    public function updateProgress(PracticeStatus $progress, string $status, ?\DateTimeImmutable $lastAttemptedAt = null): PracticeStatus;

    public function countAllAttempts(string $userId): int;

    public function countCorrectAttempts(string $userId): int;
}
