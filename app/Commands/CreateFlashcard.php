<?php

namespace App\Commands;

class CreateFlashcard implements RequiresTransaction
{
    public function __construct(
        public readonly string $question,
        public readonly string $answer,
        public readonly string $userId
    ) {}
}
