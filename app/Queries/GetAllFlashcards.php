<?php

namespace App\Queries;

class GetAllFlashcards
{
    public function __construct(
        public readonly string $userId
    ) {}
} 