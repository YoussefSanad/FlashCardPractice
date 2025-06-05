<?php

namespace App\Repositories;

use App\Models\PracticeAttempt;

interface PracticeAttemptRepository
{
    public function countByUserId(string $userId): int;
    public function deleteByUserId(string $userId): int;
    
    public function create(int $flashcardId, string $userId, string $userAnswer, bool $isCorrect): PracticeAttempt;
} 