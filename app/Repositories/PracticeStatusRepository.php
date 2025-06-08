<?php

namespace App\Repositories;

use App\Models\PracticeStatus;

interface PracticeStatusRepository
{
    public function create(int $flashcardId, string $userId, string $status, ?\DateTimeImmutable $lastAttemptedAt = null): PracticeStatus;

    public function countAttemptedFor(string $userId): int;

    public function resetFor(string $userId): int;

    public function findBy(int $flashcardId, string $userId): ?PracticeStatus;

    public function updateStatus(PracticeStatus $practiceStatus, string $newStatus, ?\DateTimeImmutable $lastAttemptedAt = null): PracticeStatus;

    public function countCorrectAttempts(string $userId): int;
}
