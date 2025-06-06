<?php

namespace App\Exceptions;

use InvalidArgumentException;

class QuestionAlreadyAnswered extends InvalidArgumentException
{
    public function __construct(int $flashcardId)
    {
        parent::__construct("Question for flashcard {$flashcardId} has already been answered correctly and cannot be practiced again.");
    }
} 