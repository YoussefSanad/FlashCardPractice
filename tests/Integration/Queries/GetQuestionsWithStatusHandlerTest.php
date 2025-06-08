<?php

namespace Integration\Queries;

use App\Commands\CreateFlashcard;
use App\Commands\SubmitAnswer;
use App\Enums\Status;
use App\Queries\GetQuestionsWithStatus;
use App\Models\PracticeStatus;
use App\ValueObjects\QuestionWithStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use League\Tactician\CommandBus;
use Tests\TestCase;

class GetQuestionsWithStatusHandlerTest extends TestCase
{
    use RefreshDatabase;

    private CommandBus $commandBus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandBus = $this->app->make(CommandBus::class);
    }

    public function test_returns_empty_array_when_no_flashcards_exist(): void
    {
        // Arrange
        $query = new GetQuestionsWithStatus('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
        $this->assertCount(0, $result);

        // Verify database is empty
        $this->assertDatabaseCount('flashcards', 0);
    }

    public function test_returns_questions_with_not_answered_status_by_default(): void
    {
        // Arrange - Create flashcards without any practice status
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('What is PHP?', 'A programming language', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('What is Laravel?', 'A PHP framework', 'user-123'));

        $query = new GetQuestionsWithStatus('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertCount(2, $result);

        $this->assertInstanceOf(QuestionWithStatus::class, $result[0]);
        $this->assertEquals($flashcard1->id, $result[0]->flashcardId);
        $this->assertEquals('user-123', $result[0]->userId);
        $this->assertEquals('What is PHP?', $result[0]->question);
        $this->assertEquals(Status::NOT_ANSWERED->value, $result[0]->status);

        $this->assertInstanceOf(QuestionWithStatus::class, $result[1]);
        $this->assertEquals($flashcard2->id, $result[1]->flashcardId);
        $this->assertEquals('user-123', $result[1]->userId);
        $this->assertEquals('What is Laravel?', $result[1]->question);
        $this->assertEquals(Status::NOT_ANSWERED->value, $result[1]->status);

        // Verify database state
        $this->assertDatabaseCount('flashcards', 2);
        $this->assertDatabaseCount('practice_statuses', 2); // Default statuses created
    }

    public function test_returns_questions_with_correct_status_after_practice(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Math Question', '42', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Science Question', 'Gravity', 'user-123'));

        // Practice with flashcards
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, '42', 'user-123')); // Correct
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Wrong', 'user-123')); // Incorrect

        $query = new GetQuestionsWithStatus('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertCount(2, $result);

        // Find questions by their flashcard IDs
        $question1 = collect($result)->firstWhere('flashcardId', $flashcard1->id);
        $question2 = collect($result)->firstWhere('flashcardId', $flashcard2->id);

        $this->assertEquals(Status::CORRECT->value, $question1->status);
        $this->assertEquals(Status::INCORRECT->value, $question2->status);

        // Verify database state
        $this->assertDatabaseCount('practice_statuses', 2);
        $this->assertDatabaseCount('practice_attempts', 2);
    }

    public function test_handles_user_with_no_practice_statuses(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2', 'user-123'));

        // Create practice statuses for different user only
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Answer 1', 'other-user'));

        $query = new GetQuestionsWithStatus('user-without-progress');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertCount(2, $result);

        foreach ($result as $question) {
            $this->assertEquals(Status::NOT_ANSWERED->value, $question->status);
            $this->assertEquals('user-without-progress', $question->userId);
        }
    }

    public function test_handles_multiline_content(): void
    {
        // Arrange
        $multilineQuestion = "What is a function in programming?\n(Explain with examples)";
        $multilineAnswer = "A function is a reusable block of code.\n\nExample:\nfunction add(a, b) {\n  return a + b;\n}";

        $flashcard = $this->commandBus->handle(new CreateFlashcard(
            $multilineQuestion,
            $multilineAnswer,
            'user-multiline'
        ));

        // Practice with it
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, $multilineAnswer, 'user-multiline'));

        $query = new GetQuestionsWithStatus('user-multiline');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertCount(1, $result);
        $question = $result[0];

        $this->assertEquals($multilineQuestion, $question->question);
        $this->assertEquals($flashcard->id, $question->flashcardId);
        $this->assertEquals(Status::CORRECT->value, $question->status);
    }

    public function test_gets_questions_with_latest_status(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('Difficult Question', 'Difficult Answer', 'user-123'));

        // Make multiple attempts - incorrect then correct
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Wrong 1', 'user-123'));
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Wrong 2', 'user-123'));
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Difficult Answer', 'user-123')); // Finally correct

        $query = new GetQuestionsWithStatus('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert - Should show the latest status (correct)
        $this->assertCount(1, $result);
        $question = $result[0];

        $this->assertEquals($flashcard->id, $question->flashcardId);
        $this->assertEquals('Difficult Question', $question->question);
        $this->assertEquals(Status::CORRECT->value, $question->status);

        // Verify database state
        $this->assertDatabaseCount('practice_attempts', 3);
    }

    public function test_gets_status_based_on_the_given_user_id(): void
    {
        // Arrange - Create a flashcard
        $flashcard = $this->commandBus->handle(new CreateFlashcard('What is PHP?', 'A programming language', 'user-123'));

        // User 1 answers correctly
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'A programming language', 'user-1'));
        // User 2 doesn't answer at all

        // Act - Query for both users
        $questionsUser1 = $this->commandBus->handle(new GetQuestionsWithStatus('user-1'));
        $questionsUser2 = $this->commandBus->handle(new GetQuestionsWithStatus('user-2'));

        // Assert
        $this->assertCount(1, $questionsUser1);
        $this->assertCount(1, $questionsUser2);

        // User 1 should have correct status
        $user1Question = $questionsUser1[0];
        $this->assertEquals($flashcard->id, $user1Question->flashcardId);
        $this->assertEquals('user-1', $user1Question->userId);
        $this->assertEquals('What is PHP?', $user1Question->question);
        $this->assertEquals(Status::CORRECT->value, $user1Question->status);

        // User 2 should have not answered status
        $user2Question = $questionsUser2[0];
        $this->assertEquals($flashcard->id, $user2Question->flashcardId);
        $this->assertEquals('user-2', $user2Question->userId);
        $this->assertEquals('What is PHP?', $user2Question->question);
        $this->assertEquals(Status::NOT_ANSWERED->value, $user2Question->status);
    }
}
