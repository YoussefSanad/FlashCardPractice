<?php

namespace Integration\Queries;

use App\Commands\CreateFlashcard;
use App\Commands\SubmitAnswer;
use App\Queries\GetStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use League\Tactician\CommandBus;
use Tests\TestCase;

class GetStatsHandlerTest extends TestCase
{
    use RefreshDatabase;

    private CommandBus $commandBus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandBus = $this->app->make(CommandBus::class);
    }

    public function test_returns_zero_stats_when_no_flashcards_exist(): void
    {
        // Arrange
        $query = new GetStats('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 0,
            'attempted_percentage' => 0,
            'correct_percentage' => 0,
            'attempted_count' => 0,
            'correct_count' => 0,
        ], $result);

        // Verify database is empty
        $this->assertDatabaseCount('flashcards', 0);
    }

    public function test_returns_correct_stats_with_no_attempts(): void
    {
        // Arrange - Create flashcards but no attempts
        $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1', 'user-123'));
        $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2', 'user-123'));
        $this->commandBus->handle(new CreateFlashcard('Question 3', 'Answer 3', 'user-123'));

        $query = new GetStats('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 3,
            'attempted_percentage' => 0.0,
            'correct_percentage' => 0.0,
            'attempted_count' => 0,
            'correct_count' => 0,
        ], $result);

        // Verify database state
        $this->assertDatabaseCount('flashcards', 3);
        $this->assertDatabaseCount('practice_statuses', 3); // Default statuses created
        $this->assertDatabaseCount('practice_attempts', 0); // No attempts made
    }

    public function test_returns_correct_stats_with_mixed_attempts(): void
    {
        // Arrange - Create flashcards
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2', 'user-123'));
        $flashcard3 = $this->commandBus->handle(new CreateFlashcard('Question 3', 'Answer 3', 'user-123'));
        $flashcard4 = $this->commandBus->handle(new CreateFlashcard('Question 4', 'Answer 4', 'user-123'));

        // Create practice statuses - mixed results
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Answer 1', 'user-123')); // Correct
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Wrong', 'user-123')); // Incorrect
        $this->commandBus->handle(new SubmitAnswer($flashcard3->id, 'Answer 3', 'user-123')); // Correct
        // flashcard4 has no attempt (STATUS_NOT_ANSWERED)

        $query = new GetStats('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 4,
            'attempted_percentage' => 75.0, // 3 out of 4 attempted
            'correct_percentage' => 50.0,   // 2 out of 4 correct
            'attempted_count' => 3,
            'correct_count' => 2,
        ], $result);
    }

    public function test_returns_correct_stats_with_all_correct_attempts(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2', 'user-123'));

        // Answer all correctly
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Answer 1', 'user-123'));
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Answer 2', 'user-123'));

        $query = new GetStats('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 2,
            'attempted_percentage' => 100.0,
            'correct_percentage' => 100.0,
            'attempted_count' => 2,
            'correct_count' => 2,
        ], $result);
    }

    public function test_returns_correct_stats_with_all_incorrect_attempts(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2', 'user-123'));

        // Answer all incorrectly
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Wrong 1', 'user-123'));
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Wrong 2', 'user-123'));

        $query = new GetStats('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 2,
            'attempted_percentage' => 100.0,
            'correct_percentage' => 0.0,
            'attempted_count' => 2,
            'correct_count' => 0,
        ], $result);
    }

    public function test_isolates_stats_by_user_id(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2', 'user-123'));

        // User 1 has correct answers
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Answer 1', 'user-1'));
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Answer 2', 'user-1'));

        // User 2 has incorrect answers
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Wrong 1', 'user-2'));
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Wrong 2', 'user-2'));

        // Act
        $statsUser1 = $this->commandBus->handle(new GetStats('user-1'));
        $statsUser2 = $this->commandBus->handle(new GetStats('user-2'));

        // Assert - User 1 has 100% correct
        $this->assertEquals([
            'total_questions' => 2,
            'attempted_percentage' => 100.0,
            'correct_percentage' => 100.0,
            'attempted_count' => 2,
            'correct_count' => 2,
        ], $statsUser1);

        // Assert - User 2 has 0% correct
        $this->assertEquals([
            'total_questions' => 2,
            'attempted_percentage' => 100.0,
            'correct_percentage' => 0.0,
            'attempted_count' => 2,
            'correct_count' => 0,
        ], $statsUser2);
    }

    public function test_handles_decimal_percentages_correctly(): void
    {
        // Arrange - Create 3 flashcards for non-even percentages
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2', 'user-123'));
        $flashcard3 = $this->commandBus->handle(new CreateFlashcard('Question 3', 'Answer 3', 'user-123'));

        // Only one correct out of 3 total (33.3%)
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Answer 1', 'user-123'));
        // No attempts for flashcard2 and flashcard3

        $query = new GetStats('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 3,
            'attempted_percentage' => 33.3, // 1 out of 3, rounded to 1 decimal
            'correct_percentage' => 33.3,   // 1 out of 3, rounded to 1 decimal
            'attempted_count' => 1,
            'correct_count' => 1,
        ], $result);
    }

    public function test_handles_user_with_no_practice_records(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2', 'user-123'));

        // Create progress for different user
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Answer 1', 'other-user'));

        $query = new GetStats('user-without-progress');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 2,
            'attempted_percentage' => 0.0,
            'correct_percentage' => 0.0,
            'attempted_count' => 0,
            'correct_count' => 0,
        ], $result);

        // Verify database state
        $this->assertDatabaseCount('practice_attempts', 1); // Only other-user's attempt
    }

    public function test_handles_multiple_attempts_shows_latest_status(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('Difficult Question', 'Difficult Answer', 'user-123'));

        // Make multiple attempts - incorrect then correct
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Wrong 1', 'user-123'));
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Wrong 2', 'user-123'));
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Difficult Answer', 'user-123')); // Finally correct

        $query = new GetStats('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        $this->assertEquals([
            'total_questions' => 1,
            'attempted_percentage' => 100.0,
            'correct_percentage' => 100.0,
            'attempted_count' => 1,
            'correct_count' => 1,
        ], $result);
    }
} 