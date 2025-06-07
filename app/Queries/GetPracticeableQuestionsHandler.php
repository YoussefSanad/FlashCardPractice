<?php

namespace App\Queries;

use App\Repositories\FlashcardRepository;
use Illuminate\Database\Eloquent\Collection;

class GetPracticeableQuestionsHandler
{
    public function __construct(
        private readonly FlashcardRepository $flashcards
    ) {}

    public function handle(GetPracticeableQuestions $query): Collection
    {
        return $this->flashcards->getPracticable($query->userId);
    }
}
