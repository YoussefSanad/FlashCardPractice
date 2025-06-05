<?php

namespace App\Queries;

use App\Models\Flashcard;
use App\Models\QuestionProgress;
use Illuminate\Database\Eloquent\Collection;

class GetPracticeableQuestionsHandler
{
    public function handle(GetPracticeableQuestions $query): Collection
    {
        return Flashcard::whereDoesntHave('questionProgress', function ($q) use ($query) {
            $q->where('user_id', $query->userId)->where('status', QuestionProgress::STATUS_CORRECT);
        })
        ->orWhereHas('questionProgress', function ($q) use ($query) {
            $q->where('user_id', $query->userId)->where('status', '!=', QuestionProgress::STATUS_CORRECT);
        })
        ->orderBy('created_at', 'desc')
        ->get();
    }
}
