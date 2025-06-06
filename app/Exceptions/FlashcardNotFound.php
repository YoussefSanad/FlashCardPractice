<?php

namespace App\Exceptions;

use InvalidArgumentException;

class FlashcardNotFound extends InvalidArgumentException
{
    public function __construct(int $flashcardId)
    {
        parent::__construct("Flashcard with ID {$flashcardId} not found.");
    }
} 