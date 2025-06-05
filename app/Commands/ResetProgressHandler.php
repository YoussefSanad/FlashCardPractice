<?php

namespace App\Commands;

use App\Repositories\PracticeAttemptRepository;
use App\Repositories\QuestionProgressRepository;

class ResetProgressHandler
{
    public function __construct(
        private readonly PracticeAttemptRepository $practiceAttempts,
        private readonly QuestionProgressRepository $questionProgress
    ) {}

    public function handle(ResetProgress $command): array
    {
        // Delete all practice attempts for this user
        $attemptsDeleted = $this->practiceAttempts->countByUserId($command->userId);
        $this->practiceAttempts->deleteByUserId($command->userId);

        // Reset all progress records to not_answered for this user
        $progressUpdated = $this->questionProgress->countNonNotAnsweredByUserId($command->userId);
        $this->questionProgress->resetProgressByUserId($command->userId);

        return [
            'attempts_deleted' => $attemptsDeleted,
            'progress_reset' => $progressUpdated,
        ];
    }
}
