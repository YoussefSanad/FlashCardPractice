<?php

namespace App\Repositories;

use App\Models\Flashcard;
use Illuminate\Database\Eloquent\Collection;
use App\ValueObjects\QuestionWithStatus;

interface FlashcardRepository
{
    public function all(): Collection;

    public function create(string $question, string $answer): Flashcard;

    public function findById(int $id): ?Flashcard;

    public function count(): int;

    /**
     * @return QuestionWithStatus[]
     */
    public function getWithStatus(string $userId): array;

    public function getPracticable(string $userId): Collection;

    public function delete(int $id): bool;
}
