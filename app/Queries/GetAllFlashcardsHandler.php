<?php

namespace App\Queries;

use App\Models\Flashcard;
use Illuminate\Database\Eloquent\Collection;

class GetAllFlashcardsHandler
{
    public function handle(GetAllFlashcards $query): Collection
    {
        return Flashcard::orderBy('created_at', 'desc')->get();
    }
}
