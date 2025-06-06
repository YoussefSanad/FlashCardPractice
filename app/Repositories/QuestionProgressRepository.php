<?php

namespace App\Repositories;

use App\Models\QuestionProgress;

interface QuestionProgressRepository
{
    public function create(int $flashcardId, string $userId, string $status, ?\DateTimeImmutable $lastAttemptedAt = null): QuestionProgress;
    
    public function countNonNotAnsweredByUserId(string $userId): int;
    
    public function resetProgressByUserId(string $userId): int;
    
    public function findByFlashcardAndUser(int $flashcardId, string $userId): ?QuestionProgress;
    
    public function updateProgress(QuestionProgress $progress, string $status, ?\DateTimeImmutable $lastAttemptedAt = null): QuestionProgress;
} 