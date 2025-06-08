<?php

namespace Tests\Unit\Commands;

use App\Commands\SubmitAnswer;
use App\Commands\SubmitAnswerHandler;
use App\Enums\Status;
use App\Exceptions\EmptyAnswer;
use App\Exceptions\FlashcardNotFound;
use App\Exceptions\InvalidFlashcardId;
use App\Exceptions\QuestionAlreadyAnsweredCorrectly;
use App\Models\PracticeAttempt;
use DateTimeImmutable;
use Tests\TestCase;
use Tests\Unit\Repositories\InMemoryFlashcardRepository;
use Tests\Unit\Repositories\InMemoryPracticeAttemptRepository;
use Tests\Unit\Repositories\InMemoryPracticeStatusRepository;
use Tests\Unit\Time\FakeClock;

class SubmitAnswerHandlerTest extends TestCase
{
    private InMemoryFlashcardRepository $flashcards;
    private InMemoryPracticeAttemptRepository $practiceAttempts;
    private InMemoryPracticeStatusRepository $practiceStatuses;
    private FakeClock $clock;
    private SubmitAnswerHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flashcards = new InMemoryFlashcardRepository();
        $this->practiceAttempts = new InMemoryPracticeAttemptRepository();
        $this->practiceStatuses = new InMemoryPracticeStatusRepository();
        $this->clock = new FakeClock(new DateTimeImmutable('2023-01-01 12:00:00'));

        $this->handler = new SubmitAnswerHandler(
            $this->flashcards,
            $this->practiceAttempts,
            $this->practiceStatuses,
            $this->clock
        );
    }

    public function test_can_submit_correct_answer_successfully(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2+2?', '4');
        $command = new SubmitAnswer($flashcard->id, '4', 'user-123');

        // Act
        $result = $this->handler->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);
        $this->assertInstanceOf(PracticeAttempt::class, $result['attempt']);

        // Verify practice attempt was created
        $attempts = $this->practiceAttempts->getAll();
        $this->assertCount(1, $attempts);
        $attempt = $attempts[0];
        $this->assertEquals($flashcard->id, $attempt->flashcard_id);
        $this->assertEquals('user-123', $attempt->user_id);
        $this->assertEquals('4', $attempt->user_answer);
        $this->assertTrue($attempt->is_correct);

        // Verify progress was created with correct status
        $progressRecords = $this->practiceStatuses->getAll();
        $this->assertCount(1, $progressRecords);
        $progress = $progressRecords[0];
        $this->assertEquals($flashcard->id, $progress->flashcard_id);
        $this->assertEquals('user-123', $progress->user_id);
        $this->assertEquals(Status::CORRECT->value, $progress->status);
        $this->assertNotNull($progress->last_attempted_at);
    }

    public function test_can_submit_incorrect_answer(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2+2?', '4');
        $command = new SubmitAnswer($flashcard->id, '5', 'user-123');

        // Act
        $result = $this->handler->handle($command);

        // Assert
        $this->assertFalse($result['is_correct']);
        $this->assertInstanceOf(PracticeAttempt::class, $result['attempt']);

        // Verify practice attempt was created
        $attempts = $this->practiceAttempts->getAll();
        $this->assertCount(1, $attempts);
        $attempt = $attempts[0];
        $this->assertEquals($flashcard->id, $attempt->flashcard_id);
        $this->assertEquals('user-123', $attempt->user_id);
        $this->assertEquals('5', $attempt->user_answer);
        $this->assertFalse($attempt->is_correct);

        // Verify progress was created with incorrect status
        $progressRecords = $this->practiceStatuses->getAll();
        $this->assertCount(1, $progressRecords);
        $progress = $progressRecords[0];
        $this->assertEquals($flashcard->id, $progress->flashcard_id);
        $this->assertEquals('user-123', $progress->user_id);
        $this->assertEquals(Status::INCORRECT->value, $progress->status);
    }

    public function test_case_insensitive_answer_comparison(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is PHP?', 'A programming language');
        $command = new SubmitAnswer($flashcard->id, 'a programming language', 'user-123');

        // Act
        $result = $this->handler->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);
    }

    public function test_trims_whitespace_from_answer(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2+2?', '4');
        $command = new SubmitAnswer($flashcard->id, '  4  ', 'user-123');

        // Act
        $result = $this->handler->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);

        // Verify trimmed answer was stored
        $attempts = $this->practiceAttempts->getAll();
        $this->assertEquals('4', $attempts[0]->user_answer);
    }

    public function test_updates_existing_progress_record(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2+2?', '4');

        // Create initial progress (incorrect)
        $this->practiceStatuses->create($flashcard->id, 'user-123', Status::INCORRECT);

        $command = new SubmitAnswer($flashcard->id, '4', 'user-123');

        // Act
        $result = $this->handler->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);

        // Verify progress was updated to correct
        $progressRecords = $this->practiceStatuses->getAll();
        $this->assertCount(1, $progressRecords);
        $progress = $progressRecords[0];
        $this->assertEquals(Status::CORRECT->value, $progress->status);
        $this->assertNotNull($progress->last_attempted_at);
    }

    public function test_throws_exception_for_already_correct_question(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2+2?', '4');
        $this->practiceStatuses->create($flashcard->id, 'user-123', Status::CORRECT);

        $command = new SubmitAnswer($flashcard->id, '4', 'user-123');

        // Act & Assert
        $this->expectException(QuestionAlreadyAnsweredCorrectly::class);

        $this->handler->handle($command);
    }

    public function test_throws_exception_for_invalid_flashcard_id(): void
    {
        // Arrange
        $command = new SubmitAnswer(0, 'answer', 'user-123');

        // Act & Assert
        $this->expectException(InvalidFlashcardId::class);

        $this->handler->handle($command);
    }

    public function test_throws_exception_for_negative_flashcard_id(): void
    {
        // Arrange
        $command = new SubmitAnswer(-1, 'answer', 'user-123');

        // Act & Assert
        $this->expectException(InvalidFlashcardId::class);

        $this->handler->handle($command);
    }

    public function test_throws_exception_for_empty_answer_with_whitespace(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2+2?', '4');
        $command = new SubmitAnswer($flashcard->id, '   ', 'user-123');

        // Act & Assert
        $this->expectException(EmptyAnswer::class);

        $this->handler->handle($command);
    }

    public function test_throws_exception_for_empty_answer(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2+2?', '4');
        $command = new SubmitAnswer($flashcard->id, '', 'user-123');

        // Act & Assert
        $this->expectException(EmptyAnswer::class);

        $this->handler->handle($command);
    }

    public function test_throws_exception_for_nonexistent_flashcard(): void
    {
        // Arrange
        $command = new SubmitAnswer(999, 'answer', 'user-123');

        // Act & Assert
        $this->expectException(FlashcardNotFound::class);

        $this->handler->handle($command);
    }

    public function test_handles_multiple_users_for_same_flashcard(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2+2?', '4');
        $command1 = new SubmitAnswer($flashcard->id, '4', 'user-1');
        $command2 = new SubmitAnswer($flashcard->id, '5', 'user-2');

        // Act
        $result1 = $this->handler->handle($command1);
        $result2 = $this->handler->handle($command2);

        // Assert
        $this->assertTrue($result1['is_correct']);
        $this->assertFalse($result2['is_correct']);

        // Verify separate progress records
        $progressRecords = $this->practiceStatuses->getAll();
        $this->assertCount(2, $progressRecords);

        $progress1 = $this->practiceStatuses->findBy($flashcard->id, 'user-1');
        $progress2 = $this->practiceStatuses->findBy($flashcard->id, 'user-2');

        $this->assertEquals(Status::CORRECT->value, $progress1->status);
        $this->assertEquals(Status::INCORRECT->value, $progress2->status);

        // Verify separate practice attempts
        $attempts = $this->practiceAttempts->getAll();
        $this->assertCount(2, $attempts);
    }

    public function test_can_retry_incorrect_answer(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2+2?', '4');

        // First attempt (incorrect)
        $command1 = new SubmitAnswer($flashcard->id, '5', 'user-123');
        $this->handler->handle($command1);

        // Second attempt (correct)
        $command2 = new SubmitAnswer($flashcard->id, '4', 'user-123');

        // Act
        $result = $this->handler->handle($command2);

        // Assert
        $this->assertTrue($result['is_correct']);

        // Verify final progress is correct
        $progress = $this->practiceStatuses->findBy($flashcard->id, 'user-123');
        $this->assertEquals(Status::CORRECT->value, $progress->status);

        // Verify both attempts were recorded
        $attempts = $this->practiceAttempts->getAll();
        $this->assertCount(2, $attempts);
    }

    public function test_handles_special_characters_in_answers(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2 + 2? (Basic math)', '4 (four)');
        $command = new SubmitAnswer($flashcard->id, '4 (four)', 'user-123');

        // Act
        $result = $this->handler->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);
    }

    public function test_creates_progress_when_none_exists(): void
    {
        // Arrange
        $flashcard = $this->flashcards->create('What is 2+2?', '4');
        $command = new SubmitAnswer($flashcard->id, '4', 'user-123');

        // Verify no progress exists initially
        $this->assertCount(0, $this->practiceStatuses->getAll());

        // Act
        $result = $this->handler->handle($command);

        // Assert
        $this->assertTrue($result['is_correct']);

        // Verify progress was created
        $progressRecords = $this->practiceStatuses->getAll();
        $this->assertCount(1, $progressRecords);
        $progress = $progressRecords[0];
        $this->assertEquals($flashcard->id, $progress->flashcard_id);
        $this->assertEquals('user-123', $progress->user_id);
        $this->assertEquals(Status::CORRECT->value, $progress->status);
    }
}
