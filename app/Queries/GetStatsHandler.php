<?php

namespace App\Queries;

use App\Repositories\FlashcardRepository;
use App\Repositories\PracticeStatusRepository;

class GetStatsHandler
{
    public function __construct(
        private readonly FlashcardRepository $flashcards,
        private readonly PracticeStatusRepository $practiceStatuses
    ) {}

    public function handle(GetStats $query): array
    {
        $totalQuestions = $this->flashcards->count();

        if ($totalQuestions === 0) {
            return [
                'total_questions' => 0,
                'attempted_percentage' => 0,
                'correct_percentage' => 0,
                'attempted_count' => 0,
                'correct_count' => 0,
            ];
        }

        $attemptedCount = $this->practiceStatuses->countAllAttempts($query->userId);
        $correctCount = $this->practiceStatuses->countCorrectAttempts($query->userId);

        $attemptedPercentage = round(($attemptedCount / $totalQuestions) * 100, 1);
        $correctPercentage = round(($correctCount / $totalQuestions) * 100, 1);

        return [
            'total_questions' => $totalQuestions,
            'attempted_percentage' => $attemptedPercentage,
            'correct_percentage' => $correctPercentage,
            'attempted_count' => $attemptedCount,
            'correct_count' => $correctCount,
        ];
    }
}
