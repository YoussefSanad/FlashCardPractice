<?php

namespace Tests\Unit\Commands;

use App\Commands\ResetProgress;
use App\Commands\ResetProgressHandler;
use App\Models\PracticeStatus;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Repositories\InMemoryPracticeStatusRepository;

class ResetProgressHandlerTest extends TestCase
{
    private InMemoryPracticeStatusRepository $practiceStatusRepository;
    private ResetProgressHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->practiceStatusRepository = new InMemoryPracticeStatusRepository();
        $this->handler = new ResetProgressHandler(
            $this->practiceStatusRepository
        );
    }

    public function test_can_reset_progress_successfully(): void
    {
        // Arrange - Create progress records
        $userId = 'user-123';

        // Create progress records
        $this->practiceStatusRepository->create(1, $userId, PracticeStatus::STATUS_CORRECT);
        $this->practiceStatusRepository->create(2, $userId, PracticeStatus::STATUS_INCORRECT);
        $this->practiceStatusRepository->create(3, 'other-user', PracticeStatus::STATUS_CORRECT);

        $command = new ResetProgress($userId);

        // Act
        $this->handler->handle($command);

        // Assert - Verify progress is reset to not_answered
        $allProgress = $this->practiceStatusRepository->getAll();
        foreach ($allProgress as $progress) {
            if ($progress->user_id === $userId) {
                $this->assertEquals(PracticeStatus::STATUS_NOT_ANSWERED, $progress->status);
                $this->assertNull($progress->last_attempted_at);
            } else {
                // Other users' progress should remain unchanged
                $this->assertEquals(PracticeStatus::STATUS_CORRECT, $progress->status);
            }
        }
    }

    public function test_reset_progress_with_no_data(): void
    {
        // Arrange
        $userId = 'user-with-no-data';
        $command = new ResetProgress($userId);

        // Act
        $this->handler->handle($command);

        // Assert - No exception should be thrown, and no progress records should exist
        $allProgress = $this->practiceStatusRepository->getAll();
        $this->assertEmpty($allProgress);
    }

    public function test_reset_progress_only_affects_target_user(): void
    {
        // Arrange
        $targetUser = 'target-user';
        $otherUser = 'other-user';

        // Create progress records for both users
        $this->practiceStatusRepository->create(1, $targetUser, PracticeStatus::STATUS_CORRECT);
        $this->practiceStatusRepository->create(2, $otherUser, PracticeStatus::STATUS_INCORRECT);

        $command = new ResetProgress($targetUser);

        // Act
        $this->handler->handle($command);

        // Assert - Check progress records
        $targetProgress = $this->practiceStatusRepository->findByFlashcardAndUser(1, $targetUser);
        $otherProgress = $this->practiceStatusRepository->findByFlashcardAndUser(2, $otherUser);

        $this->assertEquals(PracticeStatus::STATUS_NOT_ANSWERED, $targetProgress->status);
        $this->assertEquals(PracticeStatus::STATUS_INCORRECT, $otherProgress->status);
    }

    public function test_reset_progress_with_already_not_answered_records(): void
    {
        // Arrange
        $userId = 'user-123';

        // Create progress records - some already not_answered
        $this->practiceStatusRepository->create(1, $userId, PracticeStatus::STATUS_NOT_ANSWERED);
        $this->practiceStatusRepository->create(2, $userId, PracticeStatus::STATUS_CORRECT);

        $command = new ResetProgress($userId);

        // Act
        $this->handler->handle($command);

        // Assert - All records should be not_answered
        $allProgress = $this->practiceStatusRepository->getAll();
        foreach ($allProgress as $progress) {
            if ($progress->user_id === $userId) {
                $this->assertEquals(PracticeStatus::STATUS_NOT_ANSWERED, $progress->status);
                $this->assertNull($progress->last_attempted_at);
            }
        }
    }
}
