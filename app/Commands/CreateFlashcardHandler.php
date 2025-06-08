<?php

namespace App\Commands;

use App\Enums\Status;
use App\Models\Flashcard;
use App\Repositories\FlashcardRepository;
use App\Repositories\PracticeStatusRepository;
use InvalidArgumentException;

class CreateFlashcardHandler
{
    public function __construct(
        private readonly FlashcardRepository $flashcards,
        private readonly PracticeStatusRepository $practiceStatuses
    ) {}

    public function handle(CreateFlashcard $command): Flashcard
    {
        $this->validateCommand($command);
        $flashcard = $this->flashcards->create(
            trim($command->question),
            trim($command->answer)
        );

        $this->practiceStatuses->create(
            $flashcard->id,
            $command->userId,
            Status::NOT_ANSWERED
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
