<?php

namespace App\Commands;

class DeleteFlashcard implements RequiresTransaction
{
    public function __construct(
        public readonly int $id,
    ) {}
}
