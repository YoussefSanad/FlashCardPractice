<?php

namespace Tests\Unit\Queries;

use App\Queries\GetPracticeableQuestions;
use App\Queries\GetPracticeableQuestionsHandler;
use App\Models\Flashcard;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Repositories\InMemoryFlashcardRepository;

class GetPracticeableQuestionsHandlerTest extends TestCase
{
    private InMemoryFlashcardRepository $flashcardRepository;
    private GetPracticeableQuestionsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flashcardRepository = new InMemoryFlashcardRepository();
        $this->handler = new GetPracticeableQuestionsHandler(
            $this->flashcardRepository
        );
    }

    public function test_returns_empty_collection_when_no_flashcards_exist(): void
    {
        $query = new GetPracticeableQuestions('user-123');

        $flashcards = $this->handler->handle($query);

        $this->assertInstanceOf(Collection::class, $flashcards);
        $this->assertTrue($flashcards->isEmpty());
        $this->assertCount(0, $flashcards);
    }

    public function test_returns_all_flashcards_when_they_exist(): void
    {
        // Arrange
        $flashcard1 = $this->flashcardRepository->create('What is PHP?', 'A programming language');
        $flashcard2 = $this->flashcardRepository->create('What is Laravel?', 'A PHP framework');
        $flashcard3 = $this->flashcardRepository->create('What is MySQL?', 'A database');

        $query = new GetPracticeableQuestions('user-123');

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
    }

    public function test_returns_flashcards_with_correct_data(): void
    {
        // Arrange
        $flashcard = $this->flashcardRepository->create('What is 2+2?', '4');

        $query = new GetPracticeableQuestions('user-123');

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
        $flashcard = $this->flashcardRepository->create('Single Question', 'Single Answer');

        $query = new GetPracticeableQuestions('user-456');

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
        $flashcard1 = $this->flashcardRepository->create('What is 2 + 2? (Basic math)', '4 (four)');
        $flashcard2 = $this->flashcardRepository->create('Question with "quotes"', 'Answer with \'apostrophes\'');

        $query = new GetPracticeableQuestions('user-special');

        // Act
        $flashcards = $this->handler->handle($query);

        // Assert
        $this->assertCount(2, $flashcards);
        
        $this->assertEquals('What is 2 + 2? (Basic math)', $flashcards->get(0)->question);
        $this->assertEquals('4 (four)', $flashcards->get(0)->answer);
        
        $this->assertEquals('Question with "quotes"', $flashcards->get(1)->question);
        $this->assertEquals('Answer with \'apostrophes\'', $flashcards->get(1)->answer);
    }

    public function test_user_id_does_not_affect_returned_flashcards(): void
    {
        // Arrange
        $flashcard1 = $this->flashcardRepository->create('Question 1', 'Answer 1');
        $flashcard2 = $this->flashcardRepository->create('Question 2', 'Answer 2');

        // Act - Different users should get the same flashcards
        $flashcardsUser1 = $this->handler->handle(new GetPracticeableQuestions('user-1'));
        $flashcardsUser2 = $this->handler->handle(new GetPracticeableQuestions('user-2'));
        $flashcardsUser3 = $this->handler->handle(new GetPracticeableQuestions('user-3'));

        // Assert
        $this->assertCount(2, $flashcardsUser1);
        $this->assertCount(2, $flashcardsUser2);
        $this->assertCount(2, $flashcardsUser3);

        // All users should get the same flashcards
        $this->assertEquals($flashcardsUser1->pluck('id')->toArray(), $flashcardsUser2->pluck('id')->toArray());
        $this->assertEquals($flashcardsUser2->pluck('id')->toArray(), $flashcardsUser3->pluck('id')->toArray());
        
        $this->assertEquals($flashcardsUser1->pluck('question')->toArray(), $flashcardsUser2->pluck('question')->toArray());
        $this->assertEquals($flashcardsUser2->pluck('question')->toArray(), $flashcardsUser3->pluck('question')->toArray());
    }

    public function test_returns_multiple_flashcards_in_creation_order(): void
    {
        // Arrange - Create flashcards in a specific order
        $flashcard1 = $this->flashcardRepository->create('First Question', 'First Answer');
        $flashcard2 = $this->flashcardRepository->create('Second Question', 'Second Answer');
        $flashcard3 = $this->flashcardRepository->create('Third Question', 'Third Answer');

        $query = new GetPracticeableQuestions('user-order-test');

        // Act
        $flashcards = $this->handler->handle($query);

        // Assert
        $this->assertCount(3, $flashcards);
        
        // Should maintain creation order
        $this->assertEquals('First Question', $flashcards->get(0)->question);
        $this->assertEquals('Second Question', $flashcards->get(1)->question);
        $this->assertEquals('Third Question', $flashcards->get(2)->question);
        
        $this->assertEquals($flashcard1->id, $flashcards->get(0)->id);
        $this->assertEquals($flashcard2->id, $flashcards->get(1)->id);
        $this->assertEquals($flashcard3->id, $flashcards->get(2)->id);
    }

    public function test_collection_methods_work_correctly(): void
    {
        // Arrange
        $this->flashcardRepository->create('Question A', 'Answer A');
        $this->flashcardRepository->create('Question B', 'Answer B');
        $this->flashcardRepository->create('Question C', 'Answer C');

        $query = new GetPracticeableQuestions('user-collection-test');

        // Act
        $flashcards = $this->handler->handle($query);

        // Assert - Test collection methods
        $this->assertCount(3, $flashcards);
        $this->assertFalse($flashcards->isEmpty());
        $this->assertTrue($flashcards->isNotEmpty());
        
        $firstFlashcard = $flashcards->first();
        $this->assertEquals('Question A', $firstFlashcard->question);
        
        $lastFlashcard = $flashcards->last();
        $this->assertEquals('Question C', $lastFlashcard->question);
        
        // Test pluck method
        $questions = $flashcards->pluck('question')->toArray();
        $this->assertEquals(['Question A', 'Question B', 'Question C'], $questions);
    }
} 