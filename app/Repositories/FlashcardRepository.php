<?php

namespace App\Repositories;

use App\Models\Flashcard;

interface FlashcardRepository
{
    public function create(string $question, string $answer, string $userId): Flashcard;
    
    public function findById(int $id): Flashcard;
} 