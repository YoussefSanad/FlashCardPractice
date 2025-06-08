<?php

namespace App\Commands;

use App\Repositories\PracticeAttemptRepository;
use App\Repositories\PracticeStatusRepository;

class ResetProgressHandler
{
    public function __construct(
        private readonly PracticeStatusRepository $practiceStatuses
    ) {}

    public function handle(ResetProgress $command): void
    {
        $this->practiceStatuses->resetFor($command->userId);
    }
}
