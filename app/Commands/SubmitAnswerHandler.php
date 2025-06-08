<?php

namespace App\Commands;

use App\Enums\Status;
use App\Exceptions\EmptyAnswer;
use App\Exceptions\FlashcardNotFound;
use App\Exceptions\InvalidFlashcardId;
use App\Exceptions\QuestionAlreadyAnsweredCorrectly;
use App\Models\Flashcard;
use App\Models\PracticeStatus;
use App\Repositories\FlashcardRepository;
use App\Repositories\PracticeAttemptRepository;
use App\Repositories\PracticeStatusRepository;
use App\Time\Clock;

class SubmitAnswerHandler
{
    public function __construct(
        private readonly FlashcardRepository       $flashcards,
        private readonly PracticeAttemptRepository $practiceAttempts,
        private readonly PracticeStatusRepository  $practiceStatuses,
        private readonly Clock                     $clock
    ) {}

    public function handle(SubmitAnswer $command): array
    {
        $this->validateInput($command);

        $flashcard = $this->flashcards->findById($command->flashcardId);
        $practiceStatus = $this->practiceStatuses->findBy($command->flashcardId, $command->userId);

        $this->validateBusinessRules($flashcard, $practiceStatus, $command->flashcardId);

        $userAnswer = trim($command->userAnswer);
        $isCorrect = strcasecmp($userAnswer, $flashcard->answer) === 0;

        $attempt = $this->practiceAttempts->create(
            $flashcard->id,
            $command->userId,
            $userAnswer,
            $isCorrect
        );

        $newStatus = $isCorrect ? Status::CORRECT : Status::INCORRECT;
        if ($practiceStatus) {
            $this->practiceStatuses->updateStatus($practiceStatus, $newStatus, $this->clock->now());
        } else {
            $this->practiceStatuses->create($flashcard->id, $command->userId, $newStatus, $this->clock->now());
        }

        return [
            'attempt' => $attempt,
            'is_correct' => $isCorrect,
        ];
    }

    private function validateInput(SubmitAnswer $command): void
    {
        if ($command->flashcardId <= 0) {
            throw new InvalidFlashcardId($command->flashcardId);
        }

        if (empty(trim($command->userAnswer))) {
            throw new EmptyAnswer();
        }
    }

    private function validateBusinessRules(?Flashcard $flashcard, ?PracticeStatus $practiceStatus, int $flashcardId): void
    {
        if ($flashcard === null) {
            throw new FlashcardNotFound($flashcardId);
        }

        if ($practiceStatus && $practiceStatus->status === Status::CORRECT->value) {
            throw new QuestionAlreadyAnsweredCorrectly($flashcardId);
        }
    }
}
