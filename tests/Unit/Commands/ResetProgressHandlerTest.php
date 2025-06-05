<?php

namespace Tests\Unit\Commands;

use App\Commands\ResetProgress;
use App\Commands\ResetProgressHandler;
use App\Models\QuestionProgress;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Repositories\InMemoryPracticeAttemptRepository;
use Tests\Unit\Repositories\InMemoryQuestionProgressRepository;

class ResetProgressHandlerTest extends TestCase
{
    private InMemoryPracticeAttemptRepository $practiceAttemptRepository;
    private InMemoryQuestionProgressRepository $questionProgressRepository;
    private ResetProgressHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->practiceAttemptRepository = new InMemoryPracticeAttemptRepository();
        $this->questionProgressRepository = new InMemoryQuestionProgressRepository();
        $this->handler = new ResetProgressHandler(
            $this->practiceAttemptRepository,
            $this->questionProgressRepository
        );
    }

    public function test_can_reset_progress_successfully(): void
    {
        // Arrange - Create some practice attempts and progress records
        $userId = 'user-123';

        // Create practice attempts
        $this->practiceAttemptRepository->create(1, $userId, 'Answer 1', true);
        $this->practiceAttemptRepository->create(2, $userId, 'Answer 2', false);
        $this->practiceAttemptRepository->create(3, 'other-user', 'Answer 3', true);

        // Create progress records
        $this->questionProgressRepository->create(1, $userId, QuestionProgress::STATUS_CORRECT);
        $this->questionProgressRepository->create(2, $userId, QuestionProgress::STATUS_INCORRECT);
        $this->questionProgressRepository->create(3, 'other-user', QuestionProgress::STATUS_CORRECT);

        $command = new ResetProgress($userId);

        // Act
        $result = $this->handler->handle($command);

        // Assert
        $this->assertEquals(2, $result['attempts_deleted']);
        $this->assertEquals(2, $result['progress_reset']);

        // Verify practice attempts for the user are deleted
        $this->assertEquals(0, $this->practiceAttemptRepository->countByUserId($userId));
        $this->assertEquals(1, $this->practiceAttemptRepository->countByUserId('other-user'));

        // Verify progress is reset to not_answered
        $allProgress = $this->questionProgressRepository->getAll();
        foreach ($allProgress as $progress) {
            if ($progress->user_id === $userId) {
                $this->assertEquals(QuestionProgress::STATUS_NOT_ANSWERED, $progress->status);
                $this->assertNull($progress->last_attempted_at);
            } else {
                // Other users' progress should remain unchanged
                $this->assertEquals(QuestionProgress::STATUS_CORRECT, $progress->status);
            }
        }
    }

    public function test_reset_progress_with_no_data(): void
    {
        // Arrange
        $userId = 'user-with-no-data';
        $command = new ResetProgress($userId);

        // Act
        $result = $this->handler->handle($command);

        // Assert
        $this->assertEquals(0, $result['attempts_deleted']);
        $this->assertEquals(0, $result['progress_reset']);
    }

    public function test_reset_progress_only_affects_target_user(): void
    {
        // Arrange
        $targetUser = 'target-user';
        $otherUser = 'other-user';

        // Create practice attempts for both users
        $this->practiceAttemptRepository->create(1, $targetUser, 'Answer 1', true);
        $this->practiceAttemptRepository->create(2, $otherUser, 'Answer 2', false);

        // Create progress records for both users
        $this->questionProgressRepository->create(1, $targetUser, QuestionProgress::STATUS_CORRECT);
        $this->questionProgressRepository->create(2, $otherUser, QuestionProgress::STATUS_INCORRECT);

        $command = new ResetProgress($targetUser);

        // Act
        $result = $this->handler->handle($command);

        // Assert
        $this->assertEquals(1, $result['attempts_deleted']);
        $this->assertEquals(1, $result['progress_reset']);

        // Verify only target user's data is affected
        $this->assertEquals(0, $this->practiceAttemptRepository->countByUserId($targetUser));
        $this->assertEquals(1, $this->practiceAttemptRepository->countByUserId($otherUser));

        // Check progress records
        $targetProgress = $this->questionProgressRepository->findByFlashcardAndUser(1, $targetUser);
        $otherProgress = $this->questionProgressRepository->findByFlashcardAndUser(2, $otherUser);

        $this->assertEquals(QuestionProgress::STATUS_NOT_ANSWERED, $targetProgress->status);
        $this->assertEquals(QuestionProgress::STATUS_INCORRECT, $otherProgress->status);
    }

    public function test_reset_progress_with_already_not_answered_records(): void
    {
        // Arrange
        $userId = 'user-123';

        // Create practice attempts
        $this->practiceAttemptRepository->create(1, $userId, 'Answer 1', true);

        // Create progress records - some already not_answered
        $this->questionProgressRepository->create(1, $userId, QuestionProgress::STATUS_NOT_ANSWERED);
        $this->questionProgressRepository->create(2, $userId, QuestionProgress::STATUS_CORRECT);

        $command = new ResetProgress($userId);

        // Act
        $result = $this->handler->handle($command);

        // Assert
        $this->assertEquals(1, $result['attempts_deleted']);
        $this->assertEquals(1, $result['progress_reset']); // Only 1 record had non-not_answered status
    }
} 