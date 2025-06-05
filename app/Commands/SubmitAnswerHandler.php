<?php

namespace App\Commands;

use App\Models\QuestionProgress;
use App\Repositories\FlashcardRepository;
use App\Repositories\PracticeAttemptRepository;
use App\Repositories\QuestionProgressRepository;
use InvalidArgumentException;

class SubmitAnswerHandler
{
    public function __construct(
        private readonly FlashcardRepository $flashcards,
        private readonly PracticeAttemptRepository $practiceAttempts,
        private readonly QuestionProgressRepository $questionProgress
    ) {}

    public function handle(SubmitAnswer $command): array
    {
        $this->validateCommand($command);

        $flashcard = $this->flashcards->findById($command->flashcardId);

        // Check if already answered correctly for this user
        $progress = $this->questionProgress->findByFlashcardAndUser($flashcard->id, $command->userId);
        if ($progress && $progress->status === QuestionProgress::STATUS_CORRECT) {
            throw new InvalidArgumentException('This question has already been answered correctly and cannot be practiced again.');
        }

        $userAnswer = trim($command->userAnswer);
        $isCorrect = strcasecmp($userAnswer, $flashcard->answer) === 0;

        // Create practice attempt record
        $attempt = $this->practiceAttempts->create(
            $flashcard->id,
            $command->userId,
            $userAnswer,
            $isCorrect
        );

        // Update or create progress record
        $newStatus = $isCorrect ? QuestionProgress::STATUS_CORRECT : QuestionProgress::STATUS_INCORRECT;
        $lastAttemptedAt = new \DateTime();

        if ($progress) {
            $this->questionProgress->updateProgress($progress, $newStatus, $lastAttemptedAt);
        } else {
            $this->questionProgress->create($flashcard->id, $command->userId, $newStatus, $lastAttemptedAt);
        }

        return [
            'attempt' => $attempt,
            'is_correct' => $isCorrect,
            'correct_answer' => $flashcard->answer,
        ];
    }

    private function validateCommand(SubmitAnswer $command): void
    {
        if ($command->flashcardId <= 0) {
            throw new InvalidArgumentException('Invalid flashcard ID.');
        }

        if (empty(trim($command->userAnswer))) {
            throw new InvalidArgumentException('Answer cannot be empty.');
        }
    }
}
