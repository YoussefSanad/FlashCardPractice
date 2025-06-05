<?php

namespace App\Queries;

use App\Models\Flashcard;
use App\Models\QuestionProgress;

class GetStatsHandler
{
    public function handle(GetStats $query): array
    {
        $totalQuestions = Flashcard::count();

        if ($totalQuestions === 0) {
            return [
                'total_questions' => 0,
                'attempted_percentage' => 0,
                'correct_percentage' => 0,
            ];
        }

        $attemptedCount = QuestionProgress::where('user_id', $query->userId)
            ->where('status', '!=', QuestionProgress::STATUS_NOT_ANSWERED)->count();
        $correctCount = QuestionProgress::where('user_id', $query->userId)
            ->where('status', QuestionProgress::STATUS_CORRECT)->count();

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
