<?php

namespace App\Commands;

use App\Repositories\FlashcardRepository;

class DeleteFlashcardHandler
{
    public function __construct(
        private readonly FlashcardRepository $flashcards
    ) {}

    public function handle(DeleteFlashcard $command): bool
    {
        $this->validateCommand($command);
        return $this->flashcards->delete($command->id);
    }

    private function validateCommand(DeleteFlashcard $command): void
    {
       // check id is valid
    }
}
