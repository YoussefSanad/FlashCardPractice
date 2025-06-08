<?php

namespace App\Enums;

enum Status: string
{
    case NOT_ANSWERED = 'not_answered';
    case CORRECT = 'correct';
    case INCORRECT = 'incorrect';

    public function label(): string
    {
        return match ($this) {
            self::NOT_ANSWERED => 'Not answered',
            self::CORRECT => 'Correct',
            self::INCORRECT => 'Incorrect',
        };
    }
} 