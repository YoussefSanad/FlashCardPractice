<?php

namespace Tests\Unit\Queries;

use App\Queries\GetQuestionsWithStatus;
use App\Queries\GetQuestionsWithStatusHandler;
use App\Models\PracticeStatus;
use App\ValueObjects\QuestionWithStatus;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Repositories\InMemoryFlashcardRepository;
use Tests\Unit\Repositories\InMemoryPracticeStatusRepository;

class GetQuestionsWithStatusHandlerTest extends TestCase
{
    private InMemoryFlashcardRepository $flashcards;
    private InMemoryPracticeStatusRepository $practiceStatuses;
    private GetQuestionsWithStatusHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flashcards = new InMemoryFlashcardRepository();
        $this->practiceStatuses = new InMemoryPracticeStatusRepository();
        $this->flashcards->setPracticeStatusRepository($this->practiceStatuses);
        
        $this->handler = new GetQuestionsWithStatusHandler(
            $this->flashcards
        );
    }

    public function test_returns_empty_array_when_no_flashcards_exist(): void
    {
        $query = new GetQuestionsWithStatus('user-123');

        $questions = $this->handler->handle($query);

        $this->assertIsArray($questions);
        $this->assertEmpty($questions);
    }

    public function test_returns_questions_with_not_answered_status_by_default(): void
    {
        // Arrange - Create flashcards without any practice status
        $flashcard1 = $this->flashcards->create('What is PHP?', 'A programming language');
        $flashcard2 = $this->flashcards->create('What is Laravel?', 'A PHP framework');

        $query = new GetQuestionsWithStatus('user-123');

        // Act
        $questions = $this->handler->handle($query);

        // Assert
        $this->assertCount(2, $questions);
        
        $this->assertInstanceOf(QuestionWithStatus::class, $questions[0]);
        $this->assertEquals($flashcard1->id, $questions[0]->flashcardId);
        $this->assertEquals('user-123', $questions[0]->userId);
        $this->assertEquals('What is PHP?', $questions[0]->question);
        $this->assertEquals(PracticeStatus::STATUS_NOT_ANSWERED, $questions[0]->status);

        $this->assertInstanceOf(QuestionWithStatus::class, $questions[1]);
        $this->assertEquals($flashcard2->id, $questions[1]->flashcardId);
        $this->assertEquals('user-123', $questions[1]->userId);
        $this->assertEquals('What is Laravel?', $questions[1]->question);
        $this->assertEquals(PracticeStatus::STATUS_NOT_ANSWERED, $questions[1]->status);
    }

    public function test_returns_questions_with_correct_status(): void
    {
        // Arrange
        $flashcard1 = $this->flashcards->create('What is PHP?', 'A programming language');
        $flashcard2 = $this->flashcards->create('What is Laravel?', 'A PHP framework');

        // Create practice statuses
        $this->practiceStatuses->create($flashcard1->id, 'user-123', PracticeStatus::STATUS_CORRECT);
        $this->practiceStatuses->create($flashcard2->id, 'user-123', PracticeStatus::STATUS_INCORRECT);

        $query = new GetQuestionsWithStatus('user-123');

        // Act
        $questions = $this->handler->handle($query);

        // Assert
        $this->assertCount(2, $questions);
        
        $this->assertEquals(PracticeStatus::STATUS_CORRECT, $questions[0]->status);
        $this->assertEquals(PracticeStatus::STATUS_INCORRECT, $questions[1]->status);
    }

    public function test_returns_questions_with_mixed_statuses(): void
    {
        // Arrange
        $flashcard1 = $this->flashcards->create('Question 1', 'Answer 1');
        $flashcard2 = $this->flashcards->create('Question 2', 'Answer 2');
        $flashcard3 = $this->flashcards->create('Question 3', 'Answer 3');

        // Create mixed practice statuses
        $this->practiceStatuses->create($flashcard1->id, 'user-123', PracticeStatus::STATUS_CORRECT);
        $this->practiceStatuses->create($flashcard2->id, 'user-123', PracticeStatus::STATUS_INCORRECT);
        // flashcard3 has no practice status (should default to NOT_ANSWERED)

        $query = new GetQuestionsWithStatus('user-123');

        // Act
        $questions = $this->handler->handle($query);

        // Assert
        $this->assertCount(3, $questions);
        $this->assertEquals(PracticeStatus::STATUS_CORRECT, $questions[0]->status);
        $this->assertEquals(PracticeStatus::STATUS_INCORRECT, $questions[1]->status);
        $this->assertEquals(PracticeStatus::STATUS_NOT_ANSWERED, $questions[2]->status);
    }

    public function test_isolates_questions_by_user_id(): void
    {
        // Arrange
        $flashcard1 = $this->flashcards->create('Question 1', 'Answer 1');
        $flashcard2 = $this->flashcards->create('Question 2', 'Answer 2');

        // Create practice statuses for different users
        $this->practiceStatuses->create($flashcard1->id, 'user-1', PracticeStatus::STATUS_CORRECT);
        $this->practiceStatuses->create($flashcard2->id, 'user-1', PracticeStatus::STATUS_CORRECT);
        
        $this->practiceStatuses->create($flashcard1->id, 'user-2', PracticeStatus::STATUS_INCORRECT);
        $this->practiceStatuses->create($flashcard2->id, 'user-2', PracticeStatus::STATUS_INCORRECT);

        // Act
        $questionsUser1 = $this->handler->handle(new GetQuestionsWithStatus('user-1'));
        $questionsUser2 = $this->handler->handle(new GetQuestionsWithStatus('user-2'));

        // Assert
        $this->assertCount(2, $questionsUser1);
        $this->assertCount(2, $questionsUser2);

        // User 1 should have correct statuses
        $this->assertEquals(PracticeStatus::STATUS_CORRECT, $questionsUser1[0]->status);
        $this->assertEquals(PracticeStatus::STATUS_CORRECT, $questionsUser1[1]->status);
        $this->assertEquals('user-1', $questionsUser1[0]->userId);
        $this->assertEquals('user-1', $questionsUser1[1]->userId);

        // User 2 should have incorrect statuses
        $this->assertEquals(PracticeStatus::STATUS_INCORRECT, $questionsUser2[0]->status);
        $this->assertEquals(PracticeStatus::STATUS_INCORRECT, $questionsUser2[1]->status);
        $this->assertEquals('user-2', $questionsUser2[0]->userId);
        $this->assertEquals('user-2', $questionsUser2[1]->userId);
    }

    public function test_returns_correct_question_data(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2+2?', '4');
        $this->practiceStatuses->create($flashcard->id, 'user-123', PracticeStatus::STATUS_CORRECT);

        $query = new GetQuestionsWithStatus('user-123');

        // Act
        $questions = $this->handler->handle($query);

        // Assert
        $this->assertCount(1, $questions);
        $question = $questions[0];

        $this->assertEquals($flashcard->id, $question->flashcardId);
        $this->assertEquals('user-123', $question->userId);
        $this->assertEquals('What is 2+2?', $question->question);
        $this->assertEquals(PracticeStatus::STATUS_CORRECT, $question->status);
    }

    public function test_handles_user_with_no_practice_statuses(): void
    {
        // Arrange
        $flashcard1 = $this->flashcards->create('Question 1', 'Answer 1');
        $flashcard2 = $this->flashcards->create('Question 2', 'Answer 2');

        // Create practice statuses for different user only
        $this->practiceStatuses->create($flashcard1->id, 'other-user', PracticeStatus::STATUS_CORRECT);

        $query = new GetQuestionsWithStatus('user-without-progress');

        // Act
        $questions = $this->handler->handle($query);

        // Assert
        $this->assertCount(2, $questions);
        $this->assertEquals(PracticeStatus::STATUS_NOT_ANSWERED, $questions[0]->status);
        $this->assertEquals(PracticeStatus::STATUS_NOT_ANSWERED, $questions[1]->status);
        $this->assertEquals('user-without-progress', $questions[0]->userId);
        $this->assertEquals('user-without-progress', $questions[1]->userId);
    }

    public function test_handles_special_characters_in_questions(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2 + 2? (Basic math)', '4 (four)');
        $this->practiceStatuses->create($flashcard->id, 'user-123', PracticeStatus::STATUS_CORRECT);

        $query = new GetQuestionsWithStatus('user-123');

        // Act
        $questions = $this->handler->handle($query);

        // Assert
        $this->assertCount(1, $questions);
        $this->assertEquals('What is 2 + 2? (Basic math)', $questions[0]->question);
        $this->assertEquals(PracticeStatus::STATUS_CORRECT, $questions[0]->status);
    }

    public function test_question_with_status_status_method(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('Test Question', 'Test Answer');
        $this->practiceStatuses->create($flashcard->id, 'user-123', PracticeStatus::STATUS_CORRECT);

        $query = new GetQuestionsWithStatus('user-123');

        // Act
        $questions = $this->handler->handle($query);

        // Assert
        $question = $questions[0];
        $this->assertEquals('Correct', $question->status());
    }
} 