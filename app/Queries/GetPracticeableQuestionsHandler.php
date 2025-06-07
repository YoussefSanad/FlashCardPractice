<?php

namespace App\Queries;

use App\Repositories\FlashcardRepository;
use App\Models\PracticeStatus;
use Illuminate\Database\Eloquent\Collection;

class GetPracticeableQuestionsHandler
{
    public function __construct(
        private readonly FlashcardRepository $flashcards
    ) {}

    public function handle(GetPracticeableQuestions $query): Collection
    {
        return $this->flashcards->all();
    }
}
