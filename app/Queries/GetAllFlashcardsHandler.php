<?php

namespace App\Queries;

use App\Repositories\FlashcardRepository;
use Illuminate\Database\Eloquent\Collection;

class GetAllFlashcardsHandler
{
    public function __construct(
        private readonly FlashcardRepository $flashcards
    ) {}

    public function handle(GetAllFlashcards $query): Collection
    {
        return $this->flashcards->all();
    }
}
