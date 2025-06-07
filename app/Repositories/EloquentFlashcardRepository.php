<?php

namespace App\Repositories;

use App\Models\Flashcard;
use App\Models\PracticeStatus;
use App\ValueObjects\QuestionWithStatus;
use Illuminate\Database\Eloquent\Collection;

class EloquentFlashcardRepository implements FlashcardRepository
{
    public function all(): Collection
    {
        return Flashcard::all();
    }

    public function create(string $question, string $answer): Flashcard
    {
        return Flashcard::create([
            'question' => $question,
            'answer' => $answer,
        ]);
    }

    public function findById(int $id): ?Flashcard
    {
        return Flashcard::find($id);
    }

    public function count(): int
    {
        return Flashcard::count();
    }

    public function getWithStatus(string $userId): array
    {
        $flashcards = Flashcard::with(['questionProgress' => function ($query) use ($userId) {
            $query->where('user_id', $userId);
        }])->get();

        return $flashcards->map(function ($flashcard) use ($userId) {
            $progress = $flashcard->questionProgress->first();
            $status = $progress ? $progress->status : PracticeStatus::STATUS_NOT_ANSWERED;

            return new QuestionWithStatus(
                flashcardId: $flashcard->id,
                userId: $userId,
                question: $flashcard->question,
                status: $status
            );
        })->toArray();
    }

    public function getPracticable(string $userId): Collection
    {
        return Flashcard::whereDoesntHave('practiceStatuses', function ($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->where('status', PracticeStatus::STATUS_CORRECT);
        })->get();
    }
}
