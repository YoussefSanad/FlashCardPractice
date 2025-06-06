<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidFlashcardId extends InvalidArgumentException
{
    public function __construct(int $flashcardId)
    {
        parent::__construct("Invalid flashcard ID: {$flashcardId}. ID must be greater than 0.");
    }
} 