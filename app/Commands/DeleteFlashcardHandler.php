<?php

namespace App\Commands;

use App\Models\Flashcard;
use App\Repositories\FlashcardRepository;
use InvalidArgumentException;

class CreateFlashcardHandler
{
    public function __construct(
        private readonly FlashcardRepository $flashcards
    ) {}

    public function handle(CreateFlashcard $command): Flashcard
    {
        $this->validateCommand($command);
        $flashcard = $this->flashcards->create(
            trim($command->question),
            trim($command->answer)
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
