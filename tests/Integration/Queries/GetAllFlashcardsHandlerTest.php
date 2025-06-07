<?php

namespace Integration\Queries;

use App\Commands\CreateFlashcard;
use App\Commands\SubmitAnswer;
use App\Queries\GetAllFlashcards;
use App\Models\Flashcard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use League\Tactician\CommandBus;
use Tests\TestCase;

class GetAllFlashcardsHandlerTest extends TestCase
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
        $query = new GetAllFlashcards('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
        $this->assertCount(0, $result);

        // Verify database is empty
        $this->assertDatabaseCount('flashcards', 0);
    }

    public function test_returns_all_flashcards_when_they_exist(): void
    {
        // Arrange
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('What is PHP?', 'A programming language', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('What is Laravel?', 'A PHP framework', 'user-123'));
        $flashcard3 = $this->commandBus->handle(new CreateFlashcard('What is MySQL?', 'A database', 'user-123'));

        $query = new GetAllFlashcards('user-123');

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

    public function test_returns_single_flashcard_correctly(): void
    {
        // Arrange
        $flashcard = $this->commandBus->handle(new CreateFlashcard('What is 2+2?', '4', 'user-456'));
        $query = new GetAllFlashcards('user-456');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);

        $retrievedFlashcard = $result->first();
        $this->assertInstanceOf(Flashcard::class, $retrievedFlashcard);
        $this->assertEquals($flashcard->id, $retrievedFlashcard->id);
        $this->assertEquals('What is 2+2?', $retrievedFlashcard->question);
        $this->assertEquals('4', $retrievedFlashcard->answer);
        $this->assertNotNull($retrievedFlashcard->created_at);
        $this->assertNotNull($retrievedFlashcard->updated_at);
    }

    public function test_user_id_does_not_affect_returned_flashcards(): void
    {
        // Arrange - Create flashcards with different users
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Question 1', 'Answer 1', 'user-1'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Question 2', 'Answer 2', 'user-2'));
        $flashcard3 = $this->commandBus->handle(new CreateFlashcard('Question 3', 'Answer 3', 'user-3'));

        // Act - Different users querying all flashcards
        $resultUser1 = $this->commandBus->handle(new GetAllFlashcards('user-1'));
        $resultUser2 = $this->commandBus->handle(new GetAllFlashcards('user-2'));

        // Assert - All users should see all flashcards
        $this->assertCount(3, $resultUser1);
        $this->assertCount(3, $resultUser2);

        // All users should get the same flashcards
        $expectedIds = [$flashcard1->id, $flashcard2->id, $flashcard3->id];
        $this->assertEquals($expectedIds, $resultUser1->pluck('id')->sort()->values()->toArray());
        $this->assertEquals($expectedIds, $resultUser2->pluck('id')->sort()->values()->toArray());

        // Verify questions are the same for all users
        $expectedQuestions = ['Question 1', 'Question 2', 'Question 3'];
        $this->assertEquals($expectedQuestions, $resultUser1->pluck('question')->sort()->values()->toArray());
        $this->assertEquals($expectedQuestions, $resultUser2->pluck('question')->sort()->values()->toArray());
    }

    public function test_practice_status_does_not_affect_returned_flashcards(): void
    {
        // Arrange - Create flashcards and practice with them
        $flashcard1 = $this->commandBus->handle(new CreateFlashcard('Math Question', '42', 'user-123'));
        $flashcard2 = $this->commandBus->handle(new CreateFlashcard('Science Question', 'Gravity', 'user-123'));
        $flashcard3 = $this->commandBus->handle(new CreateFlashcard('History Question', '1969', 'user-123'));

        // Practice with some flashcards
        $this->commandBus->handle(new SubmitAnswer($flashcard1->id, '42', 'user-123')); // Correct
        $this->commandBus->handle(new SubmitAnswer($flashcard2->id, 'Wrong', 'user-123')); // Incorrect
        // flashcard3 remains not answered

        $query = new GetAllFlashcards('user-123');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert - All flashcards should still be returned regardless of practice status
        $this->assertCount(3, $result);
        $this->assertContains($flashcard1->id, $result->pluck('id')->toArray());
        $this->assertContains($flashcard2->id, $result->pluck('id')->toArray());
        $this->assertContains($flashcard3->id, $result->pluck('id')->toArray());

        // Verify practice statuses exist but don't affect the query
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

        $query = new GetAllFlashcards('user-multiline');

        // Act
        $result = $this->commandBus->handle($query);

        // Assert
        $this->assertCount(1, $result);
        $retrievedFlashcard = $result->first();

        $this->assertEquals($multilineQuestion, $retrievedFlashcard->question);
        $this->assertEquals($multilineAnswer, $retrievedFlashcard->answer);
        $this->assertEquals($flashcard->id, $retrievedFlashcard->id);
    }
} 