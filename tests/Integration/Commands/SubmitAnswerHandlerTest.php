<?php

namespace Integration\Commands;

use App\Commands\CreateFlashcard;
use App\Commands\SubmitAnswer;
use App\Exceptions\EmptyAnswer;
use App\Exceptions\FlashcardNotFound;
use App\Exceptions\InvalidFlashcardId;
use App\Exceptions\QuestionAlreadyAnsweredCorrectly;
use App\Models\PracticeStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use League\Tactician\CommandBus;
use Tests\TestCase;

class SubmitAnswerHandlerTest extends TestCase
{
    use RefreshDatabase;

    private CommandBus $commandBus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandBus = $this->app->make(CommandBus::class);
    }

    public function test_can_submit_correct_answer_successfully(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('What is 2+2?', '4', 'user-123'));
        $command = new SubmitAnswer($flashcard->id, '4', 'user-123');

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);
        $this->assertNotNull($result['attempt']);

        // Verify practice attempt was created
        $this->assertDatabaseHas('practice_attempts', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'user_answer' => '4',
            'is_correct' => true,
        ]);

        // Verify progress was created with correct status
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'status' => PracticeStatus::STATUS_CORRECT,
        ]);
    }

    public function test_can_submit_incorrect_answer(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('What is 2+2?', '4', 'user-123'));
        $command = new SubmitAnswer($flashcard->id, '5', 'user-123');

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertFalse($result['is_correct']);

        // Verify practice attempt was created
        $this->assertDatabaseHas('practice_attempts', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'user_answer' => '5',
            'is_correct' => false,
        ]);

        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'status' => PracticeStatus::STATUS_INCORRECT,
        ]);
    }

    public function test_case_insensitive_answer_comparison(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('What is PHP?', 'A programming language', 'user-123'));
        $command = new SubmitAnswer($flashcard->id, 'a programming language', 'user-123');

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);
    }

    public function test_trims_whitespace_from_answer(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('What is 2+2?', '4', 'user-123'));
        $command = new SubmitAnswer($flashcard->id, '  4  ', 'user-123');

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);

        $this->assertDatabaseHas('practice_attempts', [
            'user_answer' => '4', // Should be trimmed
        ]);
    }

    public function test_updates_existing_status_record(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('What is 2+2?', '4', 'user-123'));

        // Create initial status (incorrect) by submitting a wrong answer first
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, '5', 'user-123'));

        $command = new SubmitAnswer($flashcard->id, '4', 'user-123');

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);

        // Verify status was updated to correct
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'status' => PracticeStatus::STATUS_CORRECT,
        ]);

        // Should only have one status record
        $this->assertDatabaseCount('practice_statuses', 1);
    }

    public function test_throws_exception_for_already_correct_question(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('What is 2+2?', '4', 'user-123'));
        
        // Create correct status by submitting correct answer first
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, '4', 'user-123'));

        $command = new SubmitAnswer($flashcard->id, ' 4 ', 'user-123'); // Different format to avoid idempotency cache

        // Act & Assert
        $this->expectException(QuestionAlreadyAnsweredCorrectly::class);

        $this->commandBus->handle($command);
    }

    public function test_throws_exception_for_invalid_flashcard_id(): void
    {
        // Arrange
        $command = new SubmitAnswer(0, 'answer', 'user-123');

        // Act & Assert
        $this->expectException(InvalidFlashcardId::class);

        $this->commandBus->handle($command);
    }

    public function test_throws_exception_for_empty_answer(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('What is 2+2?', '4', 'user-123'));
        $command = new SubmitAnswer($flashcard->id, '   ', 'user-123');

        // Act & Assert
        $this->expectException(EmptyAnswer::class);

        $this->commandBus->handle($command);
    }

    public function test_throws_exception_for_nonexistent_flashcard(): void
    {
        // Arrange
        $command = new SubmitAnswer(999, 'answer', 'user-123');

        // Act & Assert
        $this->expectException(FlashcardNotFound::class);

        $this->commandBus->handle($command);
    }

    public function test_handles_multiple_users_for_same_flashcard(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('What is 2+2?', '4', 'user-1'));
        $command1 = new SubmitAnswer($flashcard->id, '4', 'user-1');
        $command2 = new SubmitAnswer($flashcard->id, '5', 'user-2');

        // Act
        $result1 = $this->commandBus->handle($command1);
        $result2 = $this->commandBus->handle($command2);

        // Assert
        $this->assertTrue($result1['is_correct']);
        $this->assertFalse($result2['is_correct']);

        // Verify separate status records
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-1',
            'status' => PracticeStatus::STATUS_CORRECT,
        ]);

        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-2',
            'status' => PracticeStatus::STATUS_INCORRECT,
        ]);

        // Verify separate practice attempts
        $this->assertDatabaseCount('practice_attempts', 2);
    }

    public function test_can_retry_incorrect_answer(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('What is 2+2?', '4', 'user-123'));

        // First attempt (incorrect)
        $command1 = new SubmitAnswer($flashcard->id, '5', 'user-123');
        $this->commandBus->handle($command1);

        // Second attempt (correct)
        $command2 = new SubmitAnswer($flashcard->id, '4', 'user-123');

        // Act
        $result = $this->commandBus->handle($command2);

        // Assert
        $this->assertTrue($result['is_correct']);

        // Verify final status is correct
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'status' => PracticeStatus::STATUS_CORRECT,
        ]);

        // Verify both attempts were recorded
        $this->assertDatabaseCount('practice_attempts', 2);
    }
}
