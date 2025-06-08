<?php

namespace Tests\Unit\Commands;

use App\Commands\CreateFlashcard;
use App\Commands\CreateFlashcardHandler;
use App\Models\Flashcard;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Unit\Repositories\InMemoryFlashcardRepository;

class CreateFlashcardHandlerTest extends TestCase
{
    private CreateFlashcardHandler $handler;
    private InMemoryFlashcardRepository $flashcards;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flashcards = new InMemoryFlashcardRepository();
        $this->handler = new CreateFlashcardHandler(
            $this->flashcards
        );
    }

    public function test_can_create_flashcard_successfully(): void
    {
        $command = new CreateFlashcard(
            question: 'What is Laravel?',
            answer: 'A PHP framework',
        );

        $flashcard = $this->handler->handle($command);

        $this->assertInstanceOf(Flashcard::class, $flashcard);
        $this->assertEquals('What is Laravel?', $flashcard->question);
        $this->assertEquals('A PHP framework', $flashcard->answer);
        $this->assertNotNull($flashcard->id);
    }

    public function test_trims_whitespace_from_question_and_answer(): void
    {
        $command = new CreateFlashcard(
            question: '  What is PHP?  ',
            answer: '  A programming language  ',
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
        );

        $flashcard = $this->handler->handle($command);

        $this->assertEquals($maxLengthQuestion, $flashcard->question);
        $this->assertEquals($maxLengthAnswer, $flashcard->answer);
    }


    public function test_handles_special_characters_correctly(): void
    {
        $command = new CreateFlashcard(
            question: 'What is 2 + 2? (Basic math)',
            answer: '4 (four)',
        );

        $flashcard = $this->handler->handle($command);

        $this->assertEquals('What is 2 + 2? (Basic math)', $flashcard->question);
        $this->assertEquals('4 (four)', $flashcard->answer);
    }
}
