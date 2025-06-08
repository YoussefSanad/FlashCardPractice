<?php

namespace Integration\Commands;

use App\Commands\CreateFlashcard;
use App\Commands\ResetProgress;
use App\Commands\SubmitAnswer;
use App\Enums\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use League\Tactician\CommandBus;
use Tests\TestCase;

class ResetProgressHandlerTest extends TestCase
{
    use RefreshDatabase;

    private CommandBus $commandBus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandBus = $this->app->make(CommandBus::class);
    }

    public function test_can_reset_progress_successfully(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('What is 2+2?', '4'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('What is PHP?', 'A programming language'));

        // Create practice statuses with different states by submitting answers
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, '4', 'user-123')); // Correct answer
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Wrong answer', 'user-123')); // Incorrect answer

        $command = new ResetProgress('user-123');

        // Act
        $this->commandBus->handle($command);

        // Assert - Verify all progress is reset to not_answered
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard1->id,
            'user_id' => 'user-123',
            'status' => Status::NOT_ANSWERED->value,
            'last_attempted_at' => null,
        ]);

        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard2->id,
            'user_id' => 'user-123',
            'status' => Status::NOT_ANSWERED->value,
            'last_attempted_at' => null,
        ]);
    }

    public function test_reset_progress_only_affects_target_user(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2'));

        // Create progress for target user
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Answer 1', 'target-user')); // Correct answer

        // Create progress for other user (should remain unchanged)
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Wrong answer', 'other-user')); // Incorrect answer

        $command = new ResetProgress('target-user');

        // Act
        $this->commandBus->handle($command);

        // Assert - Target user's progress is reset
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard1->id,
            'user_id' => 'target-user',
            'status' => Status::NOT_ANSWERED->value,
            'last_attempted_at' => null,
        ]);

        // Assert - Other user's progress remains unchanged
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard2->id,
            'user_id' => 'other-user',
            'status' => Status::INCORRECT->value,
        ]);

        $this->assertDatabaseMissing('practice_statuses', [
            'flashcard_id' => $flashcard2->id,
            'user_id' => 'other-user',
            'last_attempted_at' => null,
        ]);
    }

    public function test_reset_progress_with_no_existing_data(): void
    {
        // Arrange
        $command = new ResetProgress('user-with-no-data');

        // Act - Should not throw exception
        $this->commandBus->handle($command);

        // Assert - No progress records should exist
        $this->assertDatabaseCount('practice_statuses', 0);
    }

    public function test_reset_progress_with_mixed_statuses(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Q1', 'A1'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Q2', 'A2'));
        $flashcard3 = $this->commandBus->handle(new CreateFlashcard('Q3', 'A3'));

        // Create progress with different statuses by submitting answers
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Wrong answer', 'user-123')); // Incorrect answer
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'A2', 'user-123')); // Correct answer
        $this->commandBus->handle(new SubmitAnswer($flashcard3->id, 'Wrong answer', 'user-123')); // Incorrect answer

        $command = new ResetProgress('user-123');

        // Act
        $this->commandBus->handle($command);

        // Assert - All statuses should be reset to not_answered
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard1->id,
            'user_id' => 'user-123',
            'status' => Status::NOT_ANSWERED->value,
            'last_attempted_at' => null,
        ]);

        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard2->id,
            'user_id' => 'user-123',
            'status' => Status::NOT_ANSWERED->value,
            'last_attempted_at' => null,
        ]);

        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard3->id,
            'user_id' => 'user-123',
            'status' => Status::NOT_ANSWERED->value,
            'last_attempted_at' => null,
        ]);

        // Verify no incorrect or correct statuses remain
        $this->assertDatabaseMissing('practice_statuses', [
            'user_id' => 'user-123',
            'status' => Status::CORRECT->value,
        ]);

        $this->assertDatabaseMissing('practice_statuses', [
            'user_id' => 'user-123',
            'status' => Status::INCORRECT->value,
        ]);
    }

    public function test_reset_progress_with_multiple_users_same_flashcard(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('Shared Question', 'Shared Answer'));

        // Create progress for multiple users on the same flashcard
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Shared Answer', 'user-1')); // Correct answer
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Wrong answer', 'user-2')); // Incorrect answer
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Shared Answer', 'user-3')); // Correct answer

        $command = new ResetProgress('user-2');

        // Act
        $this->commandBus->handle($command);

        // Assert - Only user-2's progress is reset
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-1',
            'status' => Status::CORRECT->value,
        ]);

        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-2',
            'status' => Status::NOT_ANSWERED->value,
            'last_attempted_at' => null,
        ]);

        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-3',
            'status' => Status::CORRECT->value,
        ]);
    }

    public function test_reset_progress_preserves_practice_attempts(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('Test Question', 'Test Answer'));

        // Create practice attempts by submitting answers (these should not be affected by reset)
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Wrong Answer', 'user-123')); // Incorrect answer
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Test Answer', 'user-123')); // Correct answer

        $command = new ResetProgress('user-123');

        // Act
        $this->commandBus->handle($command);

        // Assert - Progress is reset but attempts remain
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'status' => Status::NOT_ANSWERED->value,
            'last_attempted_at' => null,
        ]);

        // Practice attempts should still exist
        $this->assertDatabaseCount('practice_attempts', 2);
        $this->assertDatabaseHas('practice_attempts', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'user_answer' => 'Wrong Answer',
            'is_correct' => false,
        ]);

        $this->assertDatabaseHas('practice_attempts', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'user_answer' => 'Test Answer',
            'is_correct' => true,
        ]);
    }

    public function test_reset_progress_multiple_times_is_idempotent(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('Test Question', 'Test Answer'));

        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Test Answer', 'user-123')); // Correct answer

        $command = new ResetProgress('user-123');

        // Act - Reset multiple times
        $this->commandBus->handle($command);
        $this->commandBus->handle($command);
        $this->commandBus->handle($command);

        // Assert - Should still have the same result
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'status' => Status::NOT_ANSWERED->value,
            'last_attempted_at' => null,
        ]);

        // Should only have one practice status record
        $this->assertDatabaseCount('practice_statuses', 1);
    }
}
