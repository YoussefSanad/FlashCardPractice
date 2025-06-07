<?php

namespace Integration\Commands;

use App\Commands\CreateFlashcard;
use App\Models\Flashcard;
use App\Models\PracticeStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use League\Tactician\CommandBus;
use Tests\TestCase;

class CreateFlashcardHandlerTest extends TestCase
{
    use RefreshDatabase;

    private CommandBus $commandBus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandBus = $this->app->make(CommandBus::class);
    }

    public function test_can_create_flashcard_successfully(): void
    {
        // Arrange
        $command = new CreateFlashcard(
            'What is Laravel?',
            'A PHP framework',
            'user-123'
        );

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertInstanceOf(Flashcard::class, $result);
        $this->assertEquals('What is Laravel?', $result->question);
        $this->assertEquals('A PHP framework', $result->answer);
        $this->assertNotNull($result->id);

        // Verify flashcard was created in database
        $this->assertDatabaseHas('flashcards', [
            'id' => $result->id,
            'question' => 'What is Laravel?',
            'answer' => 'A PHP framework',
        ]);

        // Verify initial practice status was created
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $result->id,
            'user_id' => 'user-123',
            'status' => PracticeStatus::STATUS_NOT_ANSWERED,
        ]);
    }

    public function test_trims_whitespace_from_question_and_answer(): void
    {
        // Arrange
        $command = new CreateFlashcard(
            '  What is PHP?  ',
            '  A programming language  ',
            'user-456'
        );

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertEquals('What is PHP?', $result->question);
        $this->assertEquals('A programming language', $result->answer);

        // Verify trimmed values in database
        $this->assertDatabaseHas('flashcards', [
            'question' => 'What is PHP?',
            'answer' => 'A programming language',
        ]);
    }

    public function test_throws_exception_for_empty_question(): void
    {
        // Arrange
        $command = new CreateFlashcard(
            '',
            'Valid Answer',
            'user-123'
        );

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question cannot be empty.');

        $this->commandBus->handle($command);
    }

    public function test_throws_exception_for_whitespace_only_question(): void
    {
        // Arrange
        $command = new CreateFlashcard(
            '   ',
            'Valid Answer',
            'user-123'
        );

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question cannot be empty.');

        $this->commandBus->handle($command);
    }

    public function test_throws_exception_for_empty_answer(): void
    {
        // Arrange
        $command = new CreateFlashcard(
            'Valid Question',
            '',
            'user-123'
        );

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Answer cannot be empty.');

        $this->commandBus->handle($command);
    }

    public function test_throws_exception_for_whitespace_only_answer(): void
    {
        // Arrange
        $command = new CreateFlashcard(
            'Valid Question',
            '   ',
            'user-123'
        );

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Answer cannot be empty.');

        $this->commandBus->handle($command);
    }

    public function test_throws_exception_for_question_too_long(): void
    {
        // Arrange
        $longQuestion = str_repeat('a', 65536); // One character over the limit
        $command = new CreateFlashcard(
            $longQuestion,
            'Valid Answer',
            'user-123'
        );

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question is too long (maximum 65535 characters).');

        $this->commandBus->handle($command);
    }

    public function test_throws_exception_for_answer_too_long(): void
    {
        // Arrange
        $longAnswer = str_repeat('a', 65536); // One character over the limit
        $command = new CreateFlashcard(
            'Valid Question',
            $longAnswer,
            'user-123'
        );

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Answer is too long (maximum 65535 characters).');

        $this->commandBus->handle($command);
    }

    public function test_accepts_maximum_length_question_and_answer(): void
    {
        // Arrange
        $maxLengthQuestion = str_repeat('a', 65535);
        $maxLengthAnswer = str_repeat('b', 65535);
        $command = new CreateFlashcard(
            $maxLengthQuestion,
            $maxLengthAnswer,
            'user-123'
        );

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertEquals($maxLengthQuestion, $result->question);
        $this->assertEquals($maxLengthAnswer, $result->answer);

        // Verify in database
        $this->assertDatabaseHas('flashcards', [
            'id' => $result->id,
            'question' => $maxLengthQuestion,
            'answer' => $maxLengthAnswer,
        ]);
    }

    public function test_creates_separate_flashcards_for_different_users(): void
    {
        // Arrange
        $command1 = new CreateFlashcard('Question 1', 'Answer 1', 'user-1');
        $command2 = new CreateFlashcard('Question 2', 'Answer 2', 'user-2');

        // Act
        $result1 = $this->commandBus->handle($command1);
        $result2 = $this->commandBus->handle($command2);

        // Assert
        $this->assertNotEquals($result1->id, $result2->id);
        $this->assertEquals('Question 1', $result1->question);
        $this->assertEquals('Question 2', $result2->question);

        // Verify separate database records
        $this->assertDatabaseCount('flashcards', 2);
        $this->assertDatabaseCount('practice_statuses', 2);

        // Verify separate practice statuses
        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $result1->id,
            'user_id' => 'user-1',
            'status' => PracticeStatus::STATUS_NOT_ANSWERED,
        ]);

        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $result2->id,
            'user_id' => 'user-2',
            'status' => PracticeStatus::STATUS_NOT_ANSWERED,
        ]);
    }

    public function test_handles_special_characters_correctly(): void
    {
        // Arrange
        $command = new CreateFlashcard(
            'What is 2 + 2? (Basic math)',
            '4 (four)',
            'user-special'
        );

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertEquals('What is 2 + 2? (Basic math)', $result->question);
        $this->assertEquals('4 (four)', $result->answer);

        // Verify in database
        $this->assertDatabaseHas('flashcards', [
            'question' => 'What is 2 + 2? (Basic math)',
            'answer' => '4 (four)',
        ]);
    }

    public function test_handles_unicode_characters(): void
    {
        // Arrange
        $command = new CreateFlashcard(
            '¿Cómo estás? (How are you in Spanish)',
            'Bien, gracias 😊',
            'user-unicode'
        );

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertEquals('¿Cómo estás? (How are you in Spanish)', $result->question);
        $this->assertEquals('Bien, gracias 😊', $result->answer);

        // Verify in database
        $this->assertDatabaseHas('flashcards', [
            'question' => '¿Cómo estás? (How are you in Spanish)',
            'answer' => 'Bien, gracias 😊',
        ]);
    }

    public function test_creates_multiple_flashcards_for_same_user(): void
    {
        // Arrange
        $command1 = new CreateFlashcard('What is HTML?', 'HyperText Markup Language', 'user-123');
        $command2 = new CreateFlashcard('What is CSS?', 'Cascading Style Sheets', 'user-123');

        // Act
        $result1 = $this->commandBus->handle($command1);
        $result2 = $this->commandBus->handle($command2);

        // Assert
        $this->assertNotEquals($result1->id, $result2->id);

        // Verify both flashcards exist
        $this->assertDatabaseCount('flashcards', 2);

        // Verify both practice statuses exist for the same user
        $this->assertDatabaseCount('practice_statuses', 2);

        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $result1->id,
            'user_id' => 'user-123',
        ]);

        $this->assertDatabaseHas('practice_statuses', [
            'flashcard_id' => $result2->id,
            'user_id' => 'user-123',
        ]);
    }

    public function test_validation_prevents_database_creation_on_error(): void
    {
        // Arrange
        $command = new CreateFlashcard(
            '', // Invalid empty question
            'Valid Answer',
            'user-123'
        );

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);

        try {
            $this->commandBus->handle($command);
        } catch (InvalidArgumentException $e) {
            // Verify no database records were created
            $this->assertDatabaseCount('flashcards', 0);
            $this->assertDatabaseCount('practice_statuses', 0);

            // Re-throw to satisfy expectException
            throw $e;
        }
    }

    public function test_handles_newlines_and_multiline_content(): void
    {
        // Arrange
        $multilineQuestion = "What is a function in programming?\n(Explain with examples)";
        $multilineAnswer = "A function is a reusable block of code.\n\nExample:\nfunction add(a, b) {\n  return a + b;\n}";

        $command = new CreateFlashcard(
            $multilineQuestion,
            $multilineAnswer,
            'user-multiline'
        );

        // Act
        $result = $this->commandBus->handle($command);

        // Assert
        $this->assertEquals($multilineQuestion, $result->question);
        $this->assertEquals($multilineAnswer, $result->answer);

        // Verify in database
        $this->assertDatabaseHas('flashcards', [
            'question' => $multilineQuestion,
            'answer' => $multilineAnswer,
        ]);
    }
} 