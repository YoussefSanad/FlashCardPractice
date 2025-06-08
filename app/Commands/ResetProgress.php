<?php

namespace App\Commands;

class ResetProgress implements RequiresTransaction
{
    public function __construct(
        public readonly string $userId
    ) {}
}
