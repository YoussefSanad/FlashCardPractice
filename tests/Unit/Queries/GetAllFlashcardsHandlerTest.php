<?php

namespace Tests\Unit\Queries;

use App\Queries\GetAllFlashcards;
use App\Queries\GetAllFlashcardsHandler;
use App\Models\Flashcard;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Repositories\InMemoryFlashcardRepository;

class GetAllFlashcardsHandlerTest extends TestCase
{
    private InMemoryFlashcardRepository $flashcards;
    private GetAllFlashcardsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flashcards = new InMemoryFlashcardRepository();
        $this->handler = new GetAllFlashcardsHandler(
            $this->flashcards
        );
    }

    public function test_returns_empty_collection_when_no_flashcards_exist(): void
    {
        $query = new GetAllFlashcards('user-123');

        $flashcards = $this->handler->handle($query);

        $this->assertInstanceOf(Collection::class, $flashcards);
        $this->assertTrue($flashcards->isEmpty());
        $this->assertCount(0, $flashcards);
    }

    public function test_returns_all_flashcards_when_they_exist(): void
    {
        // Arrange
        $flashcard1 = $this->flashcards->create('What is PHP?', 'A programming language');
        $flashcard2 = $this->flashcards->create('What is Laravel?', 'A PHP framework');
        $flashcard3 = $this->flashcards->create('What is MySQL?', 'A database');

        $query = new GetAllFlashcards('user-123');

        // Act
        $flashcards = $this->handler->handle($query);

        // Assert
        $this->assertInstanceOf(Collection::class, $flashcards);
        $this->assertCount(3, $flashcards);

        $this->assertInstanceOf(Flashcard::class, $flashcards->get(0));
        $this->assertInstanceOf(Flashcard::class, $flashcards->get(1));
        $this->assertInstanceOf(Flashcard::class, $flashcards->get(2));

        $this->assertEquals($flashcard1->id, $flashcards->get(0)->id);
        $this->assertEquals($flashcard2->id, $flashcards->get(1)->id);
        $this->assertEquals($flashcard3->id, $flashcards->get(2)->id);

        $this->assertEquals('What is PHP?', $flashcards->get(0)->question);
        $this->assertEquals('What is Laravel?', $flashcards->get(1)->question);
        $this->assertEquals('What is MySQL?', $flashcards->get(2)->question);

        $this->assertEquals('A programming language', $flashcards->get(0)->answer);
        $this->assertEquals('A PHP framework', $flashcards->get(1)->answer);
        $this->assertEquals('A database', $flashcards->get(2)->answer);
    }

    public function test_returns_flashcards_with_correct_data(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2+2?', '4');

        $query = new GetAllFlashcards('user-123');

        // Act
        $flashcards = $this->handler->handle($query);

        // Assert
        $this->assertCount(1, $flashcards);
        $retrievedFlashcard = $flashcards->first();

        $this->assertEquals($flashcard->id, $retrievedFlashcard->id);
        $this->assertEquals('What is 2+2?', $retrievedFlashcard->question);
        $this->assertEquals('4', $retrievedFlashcard->answer);
    }

    public function test_returns_single_flashcard(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('Single Question', 'Single Answer');

        $query = new GetAllFlashcards('user-456');

        // Act
        $flashcards = $this->handler->handle($query);

        // Assert
        $this->assertCount(1, $flashcards);
        $this->assertEquals($flashcard->id, $flashcards->first()->id);
        $this->assertEquals('Single Question', $flashcards->first()->question);
        $this->assertEquals('Single Answer', $flashcards->first()->answer);
    }

    public function test_handles_special_characters_in_flashcards(): void
    {
        // Arrange
        $flashcard1 = $this->flashcards->create('What is 2 + 2? (Basic math)', '4 (four)');
        $flashcard2 = $this->flashcards->create('Question with "quotes"', 'Answer with \'apostrophes\'');
        $flashcard3 = $this->flashcards->create('Question with émojis 🤔', 'Answer with émojis 😊');

        $query = new GetAllFlashcards('user-special');

        // Act
        $flashcards = $this->handler->handle($query);

        // Assert
        $this->assertCount(3, $flashcards);
        
        $this->assertEquals('What is 2 + 2? (Basic math)', $flashcards->get(0)->question);
        $this->assertEquals('4 (four)', $flashcards->get(0)->answer);
        
        $this->assertEquals('Question with "quotes"', $flashcards->get(1)->question);
        $this->assertEquals('Answer with \'apostrophes\'', $flashcards->get(1)->answer);
        
        $this->assertEquals('Question with émojis 🤔', $flashcards->get(2)->question);
        $this->assertEquals('Answer with émojis 😊', $flashcards->get(2)->answer);
    }

    public function test_user_id_does_not_affect_returned_flashcards(): void
    {
        // Arrange
        $flashcard1 = $this->flashcards->create('Question 1', 'Answer 1');
        $flashcard2 = $this->flashcards->create('Question 2', 'Answer 2');

        // Act - Different users should get the same flashcards
        $flashcardsUser1 = $this->handler->handle(new GetAllFlashcards('user-1'));
        $flashcardsUser2 = $this->handler->handle(new GetAllFlashcards('user-2'));
        $flashcardsUser3 = $this->handler->handle(new GetAllFlashcards('user-3'));

        // Assert
        $this->assertCount(2, $flashcardsUser1);
        $this->assertCount(2, $flashcardsUser2);
        $this->assertCount(2, $flashcardsUser3);

        // All users should get the same flashcards
        $this->assertEquals($flashcardsUser1->pluck('id')->toArray(), $flashcardsUser2->pluck('id')->toArray());
        $this->assertEquals($flashcardsUser2->pluck('id')->toArray(), $flashcardsUser3->pluck('id')->toArray());
        
        $this->assertEquals($flashcardsUser1->pluck('question')->toArray(), $flashcardsUser2->pluck('question')->toArray());
        $this->assertEquals($flashcardsUser2->pluck('question')->toArray(), $flashcardsUser3->pluck('question')->toArray());
        
        $this->assertEquals($flashcardsUser1->pluck('answer')->toArray(), $flashcardsUser2->pluck('answer')->toArray());
        $this->assertEquals($flashcardsUser2->pluck('answer')->toArray(), $flashcardsUser3->pluck('answer')->toArray());
    }

    public function test_returns_multiple_flashcards_in_creation_order(): void
    {
        // Arrange - Create flashcards in a specific order
        $flashcard1 = $this->flashcards->create('First Question', 'First Answer');
        $flashcard2 = $this->flashcards->create('Second Question', 'Second Answer');
        $flashcard3 = $this->flashcards->create('Third Question', 'Third Answer');

        $query = new GetAllFlashcards('user-order-test');

        // Act
        $flashcards = $this->handler->handle($query);

        // Assert
        $this->assertCount(3, $flashcards);
        
        // Should maintain creation order
        $this->assertEquals('First Question', $flashcards->get(0)->question);
        $this->assertEquals('Second Question', $flashcards->get(1)->question);
        $this->assertEquals('Third Question', $flashcards->get(2)->question);
        
        $this->assertEquals('First Answer', $flashcards->get(0)->answer);
        $this->assertEquals('Second Answer', $flashcards->get(1)->answer);
        $this->assertEquals('Third Answer', $flashcards->get(2)->answer);
        
        $this->assertEquals($flashcard1->id, $flashcards->get(0)->id);
        $this->assertEquals($flashcard2->id, $flashcards->get(1)->id);
        $this->assertEquals($flashcard3->id, $flashcards->get(2)->id);
    }

    public function test_collection_methods_work_correctly(): void
    {
        // Arrange
        $this->flashcards->create('Question A', 'Answer A');
        $this->flashcards->create('Question B', 'Answer B');
        $this->flashcards->create('Question C', 'Answer C');

        $query = new GetAllFlashcards('user-collection-test');

        // Act
        $flashcards = $this->handler->handle($query);

        // Assert - Test collection methods
        $this->assertCount(3, $flashcards);
        $this->assertFalse($flashcards->isEmpty());
        $this->assertTrue($flashcards->isNotEmpty());
        
        $firstFlashcard = $flashcards->first();
        $this->assertEquals('Question A', $firstFlashcard->question);
        $this->assertEquals('Answer A', $firstFlashcard->answer);
        
        $lastFlashcard = $flashcards->last();
        $this->assertEquals('Question C', $lastFlashcard->question);
        $this->assertEquals('Answer C', $lastFlashcard->answer);
        
        // Test pluck method
        $questions = $flashcards->pluck('question')->toArray();
        $this->assertEquals(['Question A', 'Question B', 'Question C'], $questions);
        
        $answers = $flashcards->pluck('answer')->toArray();
        $this->assertEquals(['Answer A', 'Answer B', 'Answer C'], $answers);
    }

    public function test_handles_long_questions_and_answers(): void
    {
        // Arrange
        $longQuestion = 'This is a very long question that contains multiple sentences and goes on for quite a while to test how the system handles longer text content. It includes various punctuation marks, numbers like 123, and special characters like @#$%^&*().';
        $longAnswer = 'This is an equally long answer that provides detailed information and explanations. It also contains multiple sentences, various punctuation marks, numbers, and special characters to ensure the system can handle complex text content properly.';
        
        $flashcard = $this->flashcards->create($longQuestion, $longAnswer);

        $query = new GetAllFlashcards('user-long-text');

        // Act
        $flashcards = $this->handler->handle($query);

        // Assert
        $this->assertCount(1, $flashcards);
        $retrievedFlashcard = $flashcards->first();
        
        $this->assertEquals($longQuestion, $retrievedFlashcard->question);
        $this->assertEquals($longAnswer, $retrievedFlashcard->answer);
        $this->assertEquals($flashcard->id, $retrievedFlashcard->id);
    }

    public function test_handles_empty_strings(): void
    {
        // Arrange - Create flashcard with empty strings (edge case)
        $flashcard = $this->flashcards->create('', '');

        $query = new GetAllFlashcards('user-empty');

        // Act
        $flashcards = $this->handler->handle($query);

        // Assert
        $this->assertCount(1, $flashcards);
        $retrievedFlashcard = $flashcards->first();
        
        $this->assertEquals('', $retrievedFlashcard->question);
        $this->assertEquals('', $retrievedFlashcard->answer);
        $this->assertEquals($flashcard->id, $retrievedFlashcard->id);
    }
} 