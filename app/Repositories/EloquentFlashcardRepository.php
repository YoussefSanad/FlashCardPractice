<?php

namespace App\Repositories;

use App\Models\Flashcard;

class EloquentFlashcardRepository implements FlashcardRepository
{
    public function create(string $question, string $answer, string $userId): Flashcard
    {
        return Flashcard::create([
            'question' => $question,
            'answer' => $answer,
        ]);
    }

    public function findById(int $id): Flashcard
    {
        return Flashcard::findOrFail($id);
    }
} 