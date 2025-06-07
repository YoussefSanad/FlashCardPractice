<?php

namespace Tests\Unit\Commands;

use App\Commands\CreateFlashcard;
use App\Commands\CreateFlashcardHandler;
use App\Models\Flashcard;
use App\Models\PracticeStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Repositories\InMemoryFlashcardRepository;
use Tests\Unit\Repositories\InMemoryPracticeStatusRepository;

class CreateFlashcardHandlerTest extends TestCase
{
    private InMemoryFlashcardRepository $flashcards;
    private InMemoryPracticeStatusRepository $practiceStatuses;
    private CreateFlashcardHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flashcards = new InMemoryFlashcardRepository();
        $this->practiceStatuses = new InMemoryPracticeStatusRepository();
        $this->handler = new CreateFlashcardHandler(
            $this->flashcards,
            $this->practiceStatuses
        );
    }

    public function test_can_create_flashcard_successfully(): void
    {
        $command = new CreateFlashcard(
            question: 'What is Laravel?',
            answer: 'A PHP framework',
            userId: 'user-123'
        );

        $flashcard = $this->handler->handle($command);

        $this->assertInstanceOf(Flashcard::class, $flashcard);
        $this->assertEquals('What is Laravel?', $flashcard->question);
        $this->assertEquals('A PHP framework', $flashcard->answer);
        $this->assertNotNull($flashcard->id);
    }

    public function test_creates_initial_progress_record(): void
    {
        $command = new CreateFlashcard(
            question: 'Test Question',
            answer: 'Test Answer',
            userId: 'user-456'
        );

        $flashcard = $this->handler->handle($command);

        $progressRecords = $this->practiceStatuses->getAll();
        $this->assertCount(1, $progressRecords);

        $progress = $progressRecords[0];
        $this->assertEquals($flashcard->id, $progress->flashcard_id);
        $this->assertEquals('user-456', $progress->user_id);
        $this->assertEquals(PracticeStatus::STATUS_NOT_ANSWERED, $progress->status);
    }

    public function test_trims_whitespace_from_question_and_answer(): void
    {
        $command = new CreateFlashcard(
            question: '  What is PHP?  ',
            answer: '  A programming language  ',
            userId: 'user-789'
        );

        $flashcard = $this->handler->handle($command);

        $this->assertEquals('What is PHP?', $flashcard->question);
        $this->assertEquals('A programming language', $flashcard->answer);
    }

    public function test_throws_exception_for_empty_question(): void
    {
        $command = new CreateFlashcard(
            question: '',
            answer: 'Valid Answer',
            userId: 'user-123'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question cannot be empty.');

        $this->handler->handle($command);
    }

    public function test_throws_exception_for_whitespace_only_question(): void
    {
        $command = new CreateFlashcard(
            question: '   ',
            answer: 'Valid Answer',
            userId: 'user-123'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question cannot be empty.');

        $this->handler->handle($command);
    }

    public function test_throws_exception_for_empty_answer(): void
    {
        $command = new CreateFlashcard(
            question: 'Valid Question',
            answer: '',
            userId: 'user-123'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Answer cannot be empty.');

        $this->handler->handle($command);
    }

    public function test_throws_exception_for_whitespace_only_answer(): void
    {
        $command = new CreateFlashcard(
            question: 'Valid Question',
            answer: '   ',
            userId: 'user-123'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Answer cannot be empty.');

        $this->handler->handle($command);
    }

    public function test_throws_exception_for_question_too_long(): void
    {
        $longQuestion = str_repeat('a', 65536); // One character over the limit

        $command = new CreateFlashcard(
            question: $longQuestion,
            answer: 'Valid Answer',
            userId: 'user-123'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question is too long (maximum 65535 characters).');

        $this->handler->handle($command);
    }

    public function test_throws_exception_for_answer_too_long(): void
    {
        $longAnswer = str_repeat('a', 65536); // One character over the limit

        $command = new CreateFlashcard(
            question: 'Valid Question',
            answer: $longAnswer,
            userId: 'user-123'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Answer is too long (maximum 65535 characters).');

        $this->handler->handle($command);
    }

    public function test_accepts_maximum_length_question_and_answer(): void
    {
        $maxLengthQuestion = str_repeat('a', 65535);
        $maxLengthAnswer = str_repeat('b', 65535);

        $command = new CreateFlashcard(
            question: $maxLengthQuestion,
            answer: $maxLengthAnswer,
            userId: 'user-123'
        );

        $flashcard = $this->handler->handle($command);

        $this->assertEquals($maxLengthQuestion, $flashcard->question);
        $this->assertEquals($maxLengthAnswer, $flashcard->answer);
    }

    public function test_creates_multiple_flashcards_with_separate_progress(): void
    {
        $command1 = new CreateFlashcard('Question 1', 'Answer 1', 'user-1');
        $command2 = new CreateFlashcard('Question 2', 'Answer 2', 'user-2');

        $flashcard1 = $this->handler->handle($command1);
        $flashcard2 = $this->handler->handle($command2);

        $this->assertNotEquals($flashcard1->id, $flashcard2->id);

        $progressRecords = $this->practiceStatuses->getAll();
        $this->assertCount(2, $progressRecords);

        $this->assertEquals($flashcard1->id, $progressRecords[0]->flashcard_id);
        $this->assertEquals($flashcard2->id, $progressRecords[1]->flashcard_id);
        $this->assertEquals('user-1', $progressRecords[0]->user_id);
        $this->assertEquals('user-2', $progressRecords[1]->user_id);
    }

    public function test_handles_special_characters_correctly(): void
    {
        $command = new CreateFlashcard(
            question: 'What is 2 + 2? (Basic math)',
            answer: '4 (four)',
            userId: 'user-special'
        );

        $flashcard = $this->handler->handle($command);

        $this->assertEquals('What is 2 + 2? (Basic math)', $flashcard->question);
        $this->assertEquals('4 (four)', $flashcard->answer);
    }
}
