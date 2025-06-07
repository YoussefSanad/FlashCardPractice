<?php

namespace Tests\Unit\Repositories;

use App\Models\Flashcard;
use App\Models\PracticeStatus;
use App\Repositories\FlashcardRepository;
use App\ValueObjects\QuestionWithStatus;
use Illuminate\Database\Eloquent\Collection;

class InMemoryFlashcardRepository implements FlashcardRepository
{
    private array $flashcards = [];
    private int $nextId = 1;

    public function all(): Collection
    {
        return new Collection($this->flashcards);
    }

    public function create(string $question, string $answer): Flashcard
    {
        $flashcard = new Flashcard([
            'question' => $question,
            'answer' => $answer,
        ]);

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

    public function count(): int
    {
        return count($this->flashcards);
    }

    public function getWithStatus(string $userId): array
    {

        return array_map(function ($flashcard) use ($userId) {
            return new QuestionWithStatus(
                flashcardId: $flashcard->id,
                userId: $userId,
                question: $flashcard->question,
                status: PracticeStatus::STATUS_NOT_ANSWERED
            );
        }, $this->flashcards);
    }

    public function getPracticable(string $userId): Collection
    {
        return new Collection($this->flashcards);
    }

    public function getAll(): array
    {
        return $this->flashcards;
    }
}
