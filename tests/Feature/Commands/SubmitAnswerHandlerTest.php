<?php

namespace Tests\Feature\Commands;

use App\Commands\SubmitAnswer;
use App\Models\Flashcard;
use App\Models\PracticeAttempt;
use App\Models\QuestionProgress;
use InvalidArgumentException;
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
        $flashcard = Flashcard::create(['question' => 'What is 2+2?', 'answer' => '4']);
        $command = new SubmitAnswer($flashcard->id, '4', 'user-123');

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);
        $this->assertEquals('4', $result['correct_answer']);
        $this->assertNotNull($result['attempt']);

        // Verify practice attempt was created
        $this->assertDatabaseHas('practice_attempts', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'user_answer' => '4',
            'is_correct' => true,
        ]);

        // Verify progress was created with correct status
        $this->assertDatabaseHas('question_progress', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'status' => QuestionProgress::STATUS_CORRECT,
        ]);
    }

    public function test_can_submit_incorrect_answer(): void
    {
        // Arrange
        $flashcard = Flashcard::create(['question' => 'What is 2+2?', 'answer' => '4']);
        $command = new SubmitAnswer($flashcard->id, '5', 'user-123');

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertFalse($result['is_correct']);
        $this->assertEquals('4', $result['correct_answer']);

        // Verify practice attempt was created
        $this->assertDatabaseHas('practice_attempts', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'user_answer' => '5',
            'is_correct' => false,
        ]);

        // Verify progress was created with incorrect status
        $this->assertDatabaseHas('question_progress', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'status' => QuestionProgress::STATUS_INCORRECT,
        ]);
    }

    public function test_case_insensitive_answer_comparison(): void
    {
        // Arrange
        $flashcard = Flashcard::create(['question' => 'What is PHP?', 'answer' => 'A programming language']);
        $command = new SubmitAnswer($flashcard->id, 'a programming language', 'user-123');

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);
    }

    public function test_trims_whitespace_from_answer(): void
    {
        // Arrange
        $flashcard = Flashcard::create(['question' => 'What is 2+2?', 'answer' => '4']);
        $command = new SubmitAnswer($flashcard->id, '  4  ', 'user-123');

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);
        
        // Verify trimmed answer was stored
        $this->assertDatabaseHas('practice_attempts', [
            'user_answer' => '4', // Should be trimmed
        ]);
    }

    public function test_updates_existing_progress_record(): void
    {
        // Arrange
        $flashcard = Flashcard::create(['question' => 'What is 2+2?', 'answer' => '4']);
        
        // Create initial progress (incorrect)
        QuestionProgress::create([
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'status' => QuestionProgress::STATUS_INCORRECT,
        ]);
        
        $command = new SubmitAnswer($flashcard->id, '4', 'user-123');

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);

        // Verify progress was updated to correct
        $this->assertDatabaseHas('question_progress', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'status' => QuestionProgress::STATUS_CORRECT,
        ]);

        // Should only have one progress record
        $this->assertDatabaseCount('question_progress', 1);
    }

    public function test_throws_exception_for_already_correct_question(): void
    {
        // Arrange
        $flashcard = Flashcard::create(['question' => 'What is 2+2?', 'answer' => '4']);
        QuestionProgress::create([
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'status' => QuestionProgress::STATUS_CORRECT,
        ]);
        
        $command = new SubmitAnswer($flashcard->id, '4', 'user-123');

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This question has already been answered correctly and cannot be practiced again.');

        $this->commandBus->handle($command);
    }

    public function test_throws_exception_for_invalid_flashcard_id(): void
    {
        // Arrange
        $command = new SubmitAnswer(0, 'answer', 'user-123');

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid flashcard ID.');

        $this->commandBus->handle($command);
    }

    public function test_throws_exception_for_empty_answer(): void
    {
        // Arrange
        $flashcard = Flashcard::create(['question' => 'What is 2+2?', 'answer' => '4']);
        $command = new SubmitAnswer($flashcard->id, '   ', 'user-123');

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Answer cannot be empty.');

        $this->commandBus->handle($command);
    }

    public function test_throws_exception_for_nonexistent_flashcard(): void
    {
        // Arrange
        $command = new SubmitAnswer(999, 'answer', 'user-123');

        // Act & Assert
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->commandBus->handle($command);
    }

    public function test_handles_multiple_users_for_same_flashcard(): void
    {
        // Arrange
        $flashcard = Flashcard::create(['question' => 'What is 2+2?', 'answer' => '4']);
        $command1 = new SubmitAnswer($flashcard->id, '4', 'user-1');
        $command2 = new SubmitAnswer($flashcard->id, '5', 'user-2');

        // Act
        $result1 = $this->commandBus->handle($command1);
        $result2 = $this->commandBus->handle($command2);

        // Assert
        $this->assertTrue($result1['is_correct']);
        $this->assertFalse($result2['is_correct']);

        // Verify separate progress records
        $this->assertDatabaseHas('question_progress', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-1',
            'status' => QuestionProgress::STATUS_CORRECT,
        ]);

        $this->assertDatabaseHas('question_progress', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-2',
            'status' => QuestionProgress::STATUS_INCORRECT,
        ]);

        // Verify separate practice attempts
        $this->assertDatabaseCount('practice_attempts', 2);
    }

    public function test_can_retry_incorrect_answer(): void
    {
        // Arrange
        $flashcard = Flashcard::create(['question' => 'What is 2+2?', 'answer' => '4']);
        
        // First attempt (incorrect)
        $command1 = new SubmitAnswer($flashcard->id, '5', 'user-123');
        $this->commandBus->handle($command1);
        
        // Second attempt (correct)
        $command2 = new SubmitAnswer($flashcard->id, '4', 'user-123');

        // Act
        $result = $this->commandBus->handle($command2);

        // Assert
        $this->assertTrue($result['is_correct']);

        // Verify final progress is correct
        $this->assertDatabaseHas('question_progress', [
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'status' => QuestionProgress::STATUS_CORRECT,
        ]);

        // Verify both attempts were recorded
        $this->assertDatabaseCount('practice_attempts', 2);
    }
} 