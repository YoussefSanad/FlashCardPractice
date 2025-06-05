<?php

namespace Feature\Repositories;

use App\Models\Flashcard;
use App\Repositories\EloquentFlashcardRepository;
use App\Repositories\FlashcardRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentFlashcardRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private FlashcardRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentFlashcardRepository();
    }

    public function test_can_create_flashcard(): void
    {
        $question = 'What is Laravel?';
        $answer = 'A PHP framework';
        $userId = 'user-123';

        $flashcard = $this->repository->create($question, $answer, $userId);

        $this->assertInstanceOf(Flashcard::class, $flashcard);
        $this->assertEquals($question, $flashcard->question);
        $this->assertEquals($answer, $flashcard->answer);
        $this->assertNotNull($flashcard->id);
        $this->assertNotNull($flashcard->created_at);

        // Verify it's actually in the database
        $this->assertDatabaseHas('flashcards', [
            'id' => $flashcard->id,
            'question' => $question,
            'answer' => $answer,
        ]);
    }

    public function test_can_create_multiple_flashcards(): void
    {
        $flashcard1 = $this->repository->create('Question 1', 'Answer 1', 'user-1');
        $flashcard2 = $this->repository->create('Question 2', 'Answer 2', 'user-2');

        $this->assertNotEquals($flashcard1->id, $flashcard2->id);
        $this->assertEquals('Question 1', $flashcard1->question);
        $this->assertEquals('Question 2', $flashcard2->question);

        $this->assertDatabaseCount('flashcards', 2);
    }

    public function test_can_create_flashcards_with_special_characters(): void
    {
        $question = 'What is 2 + 2? (Math question)';
        $answer = '4 (four)';

        $flashcard = $this->repository->create($question, $answer, 'user-123');

        $this->assertEquals($question, $flashcard->question);
        $this->assertEquals($answer, $flashcard->answer);
    }

    public function test_can_create_flashcards_with_duplicate_questions(): void
    {
        // Since there's no unique constraint on questions, duplicates should be allowed at the repository level
        $flashcard1 = $this->repository->create('Duplicate Question', 'Answer 1', 'user-1');
        $flashcard2 = $this->repository->create('Duplicate Question', 'Answer 2', 'user-2');

        $this->assertNotEquals($flashcard1->id, $flashcard2->id);
        $this->assertEquals('Duplicate Question', $flashcard1->question);
        $this->assertEquals('Duplicate Question', $flashcard2->question);
        $this->assertEquals('Answer 1', $flashcard1->answer);
        $this->assertEquals('Answer 2', $flashcard2->answer);

        $this->assertDatabaseCount('flashcards', 2);
    }

    public function test_can_find_flashcard_by_id(): void
    {
        $flashcard = $this->repository->create('Test Question', 'Test Answer', 'user-123');

        $foundFlashcard = $this->repository->findById($flashcard->id);

        $this->assertEquals($flashcard->id, $foundFlashcard->id);
        $this->assertEquals('Test Question', $foundFlashcard->question);
        $this->assertEquals('Test Answer', $foundFlashcard->answer);
    }

    public function test_find_by_id_throws_exception_for_nonexistent_flashcard(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->repository->findById(999);
    }
}
