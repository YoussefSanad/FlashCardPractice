<?php

namespace App\Commands;

use App\Repositories\PracticeAttemptRepository;
use App\Repositories\PracticeStatusRepository;

class ResetProgressHandler
{
    public function __construct(
        private readonly PracticeStatusRepository $questionProgress
    ) {}

    public function handle(ResetProgress $command): void
    {
        $this->questionProgress->resetProgressByUserId($command->userId);
    }
}
