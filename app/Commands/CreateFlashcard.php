<?php

namespace App\Commands;

class CreateFlashcard
{
    public function __construct(
        public readonly string $question,
        public readonly string $answer,
        public readonly string $userId
    ) {}
} 