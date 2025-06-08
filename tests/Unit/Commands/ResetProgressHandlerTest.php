<?php

namespace Tests\Unit\Commands;

use App\Commands\ResetProgress;
use App\Commands\ResetProgressHandler;
use App\Enums\Status;
use App\Models\PracticeStatus;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Repositories\InMemoryPracticeStatusRepository;

class ResetProgressHandlerTest extends TestCase
{
    private InMemoryPracticeStatusRepository $practiceStatuses;
    private ResetProgressHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->practiceStatuses = new InMemoryPracticeStatusRepository();
        $this->handler = new ResetProgressHandler(
            $this->practiceStatuses
        );
    }

    public function test_can_reset_progress_successfully(): void
    {
        // Arrange - Create progress records
        $userId = 'user-123';

        // Create progress records
        $this->practiceStatuses->create(1, $userId, Status::CORRECT);
        $this->practiceStatuses->create(2, $userId, Status::INCORRECT);
        $this->practiceStatuses->create(3, 'other-user', Status::CORRECT);

        $command = new ResetProgress($userId);

        // Act
        $this->handler->handle($command);

        // Assert - Verify progress is reset to not_answered
        $allProgress = $this->practiceStatuses->getAll();
        foreach ($allProgress as $progress) {
            if ($progress->user_id === $userId) {
                $this->assertEquals(Status::NOT_ANSWERED->value, $progress->status);
                $this->assertNull($progress->last_attempted_at);
            } else {
                // Other users' progress should remain unchanged
                $this->assertEquals(Status::CORRECT->value, $progress->status);
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
        $allProgress = $this->practiceStatuses->getAll();
        $this->assertEmpty($allProgress);
    }

    public function test_reset_progress_only_affects_target_user(): void
    {
        // Arrange
        $targetUser = 'target-user';
        $otherUser = 'other-user';

        // Create progress records for both users
        $this->practiceStatuses->create(1, $targetUser, Status::CORRECT);
        $this->practiceStatuses->create(2, $otherUser, Status::INCORRECT);

        $command = new ResetProgress($targetUser);

        // Act
        $this->handler->handle($command);

        // Assert - Check progress records
        $targetProgress = $this->practiceStatuses->findBy(1, $targetUser);
        $otherProgress = $this->practiceStatuses->findBy(2, $otherUser);

        $this->assertEquals(Status::NOT_ANSWERED->value, $targetProgress->status);
        $this->assertEquals(Status::INCORRECT->value, $otherProgress->status);
    }

    public function test_reset_progress_with_already_not_answered_records(): void
    {
        // Arrange
        $userId = 'user-123';

        // Create progress records - some already not_answered
        $this->practiceStatuses->create(1, $userId, Status::NOT_ANSWERED);
        $this->practiceStatuses->create(2, $userId, Status::CORRECT);

        $command = new ResetProgress($userId);

        // Act
        $this->handler->handle($command);

        // Assert - All records should be not_answered
        $allProgress = $this->practiceStatuses->getAll();
        foreach ($allProgress as $progress) {
            if ($progress->user_id === $userId) {
                $this->assertEquals(Status::NOT_ANSWERED->value, $progress->status);
                $this->assertNull($progress->last_attempted_at);
            }
        }
    }
}
