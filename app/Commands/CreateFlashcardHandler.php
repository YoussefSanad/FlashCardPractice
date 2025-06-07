<?php

namespace App\Commands;

use App\Models\Flashcard;
use App\Models\PracticeStatus;
use App\Repositories\FlashcardRepository;
use App\Repositories\PracticeStatusRepository;
use InvalidArgumentException;

class CreateFlashcardHandler
{
    public function __construct(
        private readonly FlashcardRepository $flashcards,
        private readonly PracticeStatusRepository $questionProgress
    ) {}

    public function handle(CreateFlashcard $command): Flashcard
    {
        $this->validateCommand($command);
        $flashcard = $this->flashcards->create(
            trim($command->question),
            trim($command->answer)
        );

        // Create initial progress record
        $this->questionProgress->create(
            $flashcard->id,
            $command->userId,
            PracticeStatus::STATUS_NOT_ANSWERED
        );

        return $flashcard;
    }

    private function validateCommand(CreateFlashcard $command): void
    {
        if (empty(trim($command->question))) {
            throw new InvalidArgumentException('Question cannot be empty.');
        }

        if (empty(trim($command->answer))) {
            throw new InvalidArgumentException('Answer cannot be empty.');
        }

        if (strlen($command->question) > 65535) {
            throw new InvalidArgumentException('Question is too long (maximum 65535 characters).');
        }

        if (strlen($command->answer) > 65535) {
            throw new InvalidArgumentException('Answer is too long (maximum 65535 characters).');
        }
    }
}
