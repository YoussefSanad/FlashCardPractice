<?php

namespace App\Queries;

use App\Repositories\FlashcardRepository;
use App\ValueObjects\QuestionWithStatus;

class GetQuestionsWithStatusHandler
{
    public function __construct(
        private readonly FlashcardRepository $flashcards,
    ) {}

    /**
     * @return QuestionWithStatus[]
     */
    public function handle(GetQuestionsWithStatus $query): array
    {
        return $this->flashcards->getWithStatus($query->userId);
    }
}
