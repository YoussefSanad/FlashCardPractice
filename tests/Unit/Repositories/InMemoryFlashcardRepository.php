<?php

namespace Tests\Unit\Repositories;

use App\Models\Flashcard;
use App\Repositories\FlashcardRepository;

class InMemoryFlashcardRepository implements FlashcardRepository
{
    private array $flashcards = [];
    private int $nextId = 1;

    public function create(string $question, string $answer): Flashcard
    {
        $flashcard = new Flashcard([
            'question' => $question,
            'answer' => $answer,
        ]);

        // Manually set the ID since we're not using database
        $flashcard->setAttribute('id', value: $this->nextId++);

        $this->flashcards[] = $flashcard;

        return $flashcard;
    }

    public function findById(int $id): ?Flashcard
    {
        foreach ($this->flashcards as $flashcard) {
            if ($flashcard->id === $id) {
                return $flashcard;
            }
        }
        
        return null;
    }

    public function getAll(): array
    {
        return $this->flashcards;
    }
}
