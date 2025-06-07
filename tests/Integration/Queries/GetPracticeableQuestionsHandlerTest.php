<?php

namespace Integration\Queries;

use App\Commands\CreateFlashcard;
use App\Commands\SubmitAnswer;
use App\Queries\GetPracticeableQuestions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use League\Tactician\CommandBus;
use Tests\TestCase;

class GetPracticeableQuestionsHandlerTest extends TestCase
{
    use RefreshDatabase;

    private CommandBus $commandBus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandBus = $this->app->make(CommandBus::class);
    }

    public function test_returns_empty_collection_when_no_flashcards_exist(): void
    {
        // Arrange
        $query = new GetPracticeableQuestions('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
        $this->assertCount(0, $result);

        // Verify database is empty
        $this->assertDatabaseCount('flashcards', 0);
    }

    public function test_returns_all_flashcards_when_no_practice_attempts_exist(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('What is PHP?', 'A programming language', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('What is Laravel?', 'A PHP framework', 'user-123'));
        $flashcard3 = $this->commandBus->handle(new CreateFlashcard('What is MySQL?', 'A database', 'user-123'));

        $query = new GetPracticeableQuestions('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        // Verify all flashcards are returned
        $this->assertContains($flashcard1->id, $result->pluck('id')->toArray());
        $this->assertContains($flashcard2->id, $result->pluck('id')->toArray());
        $this->assertContains($flashcard3->id, $result->pluck('id')->toArray());

        // Verify content
        $this->assertContains('What is PHP?', $result->pluck('question')->toArray());
        $this->assertContains('What is Laravel?', $result->pluck('question')->toArray());
        $this->assertContains('What is MySQL?', $result->pluck('question')->toArray());

        $this->assertContains('A programming language', $result->pluck('answer')->toArray());
        $this->assertContains('A PHP framework', $result->pluck('answer')->toArray());
        $this->assertContains('A database', $result->pluck('answer')->toArray());

        // Verify database state
        $this->assertDatabaseCount('flashcards', 3);
    }

    public function test_excludes_correctly_answered_flashcards(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Math Question', '42', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Science Question', 'Gravity', 'user-123'));
        $flashcard3 = $this->commandBus->handle(new CreateFlashcard('History Question', '1969', 'user-123'));

        // Practice with flashcards
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, '42', 'user-123')); // Correct
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Wrong', 'user-123')); // Incorrect
        // flashcard3 remains not answered

        $query = new GetPracticeableQuestions('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert - Should exclude correctly answered flashcard
        $this->assertCount(2, $result);
        $this->assertNotContains($flashcard1->id, $result->pluck('id')->toArray()); // Correct - excluded
        $this->assertContains($flashcard2->id, $result->pluck('id')->toArray()); // Incorrect - included
        $this->assertContains($flashcard3->id, $result->pluck('id')->toArray()); // Not answered - included

        // Verify content
        $this->assertNotContains('Math Question', $result->pluck('question')->toArray()); // Excluded
        $this->assertContains('Science Question', $result->pluck('question')->toArray()); // Included
        $this->assertContains('History Question', $result->pluck('question')->toArray()); // Included

        // Verify database state
        $this->assertDatabaseCount('flashcards', 3);
        $this->assertDatabaseCount('practice_statuses', 3); // All should have status records
        $this->assertDatabaseCount('practice_attempts', 2); // Only 2 attempts made
    }

    public function test_includes_incorrectly_answered_flashcards(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2', 'user-123'));

        // Submit incorrect answers
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Wrong 1', 'user-123'));
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Wrong 2', 'user-123'));

        $query = new GetPracticeableQuestions('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert - Both incorrectly answered flashcards should be included
        $this->assertCount(2, $result);
        $this->assertContains($flashcard1->id, $result->pluck('id')->toArray());
        $this->assertContains($flashcard2->id, $result->pluck('id')->toArray());

        // Verify database state shows incorrect attempts
        $this->assertDatabaseCount('practice_attempts', 2);
    }

    public function test_gets_user_specirfic_practiceable_questions(): void
    {
        // Arrange - Create flashcards and practice with different users
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1', 'user-1'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2', 'user-2'));
        $flashcard3 = $this->commandBus->handle(new CreateFlashcard('Question 3', 'Answer 3', 'user-3'));

        // User 1 answers question 1 correctly
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Answer 1', 'user-1'));
        
        // User 2 answers question 2 correctly
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Answer 2', 'user-2'));

        // Act - Different users querying practiceable flashcards
        $resultUser1 = $this->commandBus->handle(new GetPracticeableQuestions('user-1'));
        $resultUser2 = $this->commandBus->handle(new GetPracticeableQuestions('user-2'));
        $resultUser3 = $this->commandBus->handle(new GetPracticeableQuestions('user-3'));

        // Assert - User isolation should work
        $this->assertCount(2, $resultUser1); // Excludes flashcard1 (answered correctly)
        $this->assertCount(2, $resultUser2); // Excludes flashcard2 (answered correctly)
        $this->assertCount(3, $resultUser3); // Includes all (no correct answers)

        // User 1 should not see question 1 (answered correctly)
        $this->assertNotContains($flashcard1->id, $resultUser1->pluck('id')->toArray());
        $this->assertContains($flashcard2->id, $resultUser1->pluck('id')->toArray());
        $this->assertContains($flashcard3->id, $resultUser1->pluck('id')->toArray());

        // User 2 should not see question 2 (answered correctly)
        $this->assertContains($flashcard1->id, $resultUser2->pluck('id')->toArray());
        $this->assertNotContains($flashcard2->id, $resultUser2->pluck('id')->toArray());
        $this->assertContains($flashcard3->id, $resultUser2->pluck('id')->toArray());

        // User 3 should see all questions (no correct answers)
        $this->assertContains($flashcard1->id, $resultUser3->pluck('id')->toArray());
        $this->assertContains($flashcard2->id, $resultUser3->pluck('id')->toArray());
        $this->assertContains($flashcard3->id, $resultUser3->pluck('id')->toArray());
    }

    public function test_returns_empty_when_all_flashcards_answered_correctly(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2', 'user-123'));

        // Answer all correctly
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Answer 1', 'user-123'));
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Answer 2', 'user-123'));

        $query = new GetPracticeableQuestions('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert - Should return empty collection when all are correct
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
        $this->assertCount(0, $result);

        // Verify database state
        $this->assertDatabaseCount('flashcards', 2);
        $this->assertDatabaseCount('practice_attempts', 2);
    }

    public function test_handles_mixed_practice_statuses(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Correct Question', 'Correct Answer', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Incorrect Question', 'Incorrect Answer', 'user-123'));
        $flashcard3 = $this->commandBus->handle(new CreateFlashcard('Not Answered Question', 'Not Answered Answer', 'user-123'));

        // Practice with mixed results
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, 'Correct Answer', 'user-123')); // Correct
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Wrong', 'user-123')); // Incorrect
        // flashcard3 remains not answered

        $query = new GetPracticeableQuestions('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert - Should include incorrect and not answered, exclude correct
        $this->assertCount(2, $result);
        $this->assertNotContains($flashcard1->id, $result->pluck('id')->toArray()); // Correct - excluded
        $this->assertContains($flashcard2->id, $result->pluck('id')->toArray()); // Incorrect - included
        $this->assertContains($flashcard3->id, $result->pluck('id')->toArray()); // Not answered - included

        // Verify content
        $this->assertNotContains('Correct Question', $result->pluck('question')->toArray());
        $this->assertContains('Incorrect Question', $result->pluck('question')->toArray());
        $this->assertContains('Not Answered Question', $result->pluck('question')->toArray());

        // Verify database state
        $this->assertDatabaseCount('practice_statuses', 3); // All should have status records
        $this->assertDatabaseCount('practice_attempts', 2); // Only 2 attempts made
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

        $query = new GetPracticeableQuestions('user-multiline');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertCount(1, $result);
        $retrievedFlashcard = $result->first();

        $this->assertEquals($multilineQuestion, $retrievedFlashcard->question);
        $this->assertEquals($multilineAnswer, $retrievedFlashcard->answer);
        $this->assertEquals($flashcard->id, $retrievedFlashcard->id);
    }

    public function test_multiple_incorrect_attempts_still_included(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('Difficult Question', 'Difficult Answer', 'user-123'));

        // Make multiple incorrect attempts
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Wrong 1', 'user-123'));
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Wrong 2', 'user-123'));
        $this->commandBus->handle(new SubmitAnswer($flashcard->id, 'Wrong 3', 'user-123'));

        $query = new GetPracticeableQuestions('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert - Should still be included despite multiple incorrect attempts
        $this->assertCount(1, $result);
        $this->assertEquals($flashcard->id, $result->first()->id);
        $this->assertEquals('Difficult Question', $result->first()->question);

        // Verify database state
        $this->assertDatabaseCount('practice_attempts', 3);
    }
} 