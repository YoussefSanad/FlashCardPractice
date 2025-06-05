<?php

namespace App\Queries;

use App\Models\Flashcard;
use App\Models\QuestionProgress;

class GetPracticeProgressHandler
{
    public function handle(GetPracticeProgress $query): array
    {
        $flashcards = Flashcard::with(['questionProgress' => function ($q) use ($query) {
            $q->where('user_id', $query->userId);
        }])->orderBy('created_at', 'desc')->get();

        $progressData = [];
        $totalQuestions = $flashcards->count();
        $correctAnswers = 0;

        foreach ($flashcards as $flashcard) {
            $progress = $flashcard->questionProgress->first();
            $status = $progress ? $progress->status : QuestionProgress::STATUS_NOT_ANSWERED;

            if ($status === QuestionProgress::STATUS_CORRECT) {
                $correctAnswers++;
            }

            $progressData[] = [
                'id' => $flashcard->id,
                'question' => $flashcard->question,
                'status' => $this->formatStatus($status),
            ];
        }

        $completionPercentage = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 1) : 0;

        return [
            'progress' => $progressData,
            'completion_percentage' => $completionPercentage,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
        ];
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            QuestionProgress::STATUS_NOT_ANSWERED => 'Not answered',
            QuestionProgress::STATUS_CORRECT => 'Correct',
            QuestionProgress::STATUS_INCORRECT => 'Incorrect',
            default => 'Unknown',
        };
    }
}
