<?php

namespace Integration\Repositories;

use App\Enums\Status;
use App\Models\Flashcard;
use App\Models\PracticeStatus;
use App\Repositories\EloquentFlashcardRepository;
use App\Repositories\EloquentPracticeStatusRepository;
use App\Repositories\FlashcardRepository;
use App\Repositories\PracticeStatusRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentFlashcardRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private FlashcardRepository $repository;
    private PracticeStatusRepository $progressRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentFlashcardRepository();
        $this->progressRepository = new EloquentPracticeStatusRepository();
    }

    public function test_can_create_flashcard(): void
    {
        $question = 'What is Laravel?';
        $answer = 'A PHP framework';

        $flashcard = $this->repository->create($question, $answer);

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
        $flashcard1 = $this->repository->create('Question 1', 'Answer 1');
        $flashcard2 = $this->repository->create('Question 2', 'Answer 2');

        $this->assertNotEquals($flashcard1->id, $flashcard2->id);
        $this->assertEquals('Question 1', $flashcard1->question);
        $this->assertEquals('Question 2', $flashcard2->question);

        $this->assertDatabaseCount('flashcards', 2);
    }

    public function test_can_create_flashcards_with_special_characters(): void
    {
        $question = 'What is 2 + 2? (Math question)';
        $answer = '4 (four)';

        $flashcard = $this->repository->create($question, $answer);

        $this->assertEquals($question, $flashcard->question);
        $this->assertEquals($answer, $flashcard->answer);
    }

    public function test_can_create_flashcards_with_duplicate_questions(): void
    {
        // Since there's no unique constraint on questions, duplicates should be allowed at the repository level
        $flashcard1 = $this->repository->create('Duplicate Question', 'Answer 1');
        $flashcard2 = $this->repository->create('Duplicate Question', 'Answer 2');

        $this->assertNotEquals($flashcard1->id, $flashcard2->id);
        $this->assertEquals('Duplicate Question', $flashcard1->question);
        $this->assertEquals('Duplicate Question', $flashcard2->question);
        $this->assertEquals('Answer 1', $flashcard1->answer);
        $this->assertEquals('Answer 2', $flashcard2->answer);

        $this->assertDatabaseCount('flashcards', 2);
    }

    public function test_can_find_flashcard_by_id(): void
    {
        $flashcard = $this->repository->create('Test Question', 'Test Answer');

        $foundFlashcard = $this->repository->findById($flashcard->id);

        $this->assertEquals($flashcard->id, $foundFlashcard->id);
        $this->assertEquals('Test Question', $foundFlashcard->question);
        $this->assertEquals('Test Answer', $foundFlashcard->answer);
    }

    public function test_find_by_id_returns_null_for_nonexistent_flashcard(): void
    {
        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function test_get_practicable_returns_flashcards_with_no_correct_answers(): void
    {
        // Create flashcards
        $flashcard1 = $this->repository->create('Question 1', 'Answer 1');
        $flashcard2 = $this->repository->create('Question 2', 'Answer 2');
        $flashcard3 = $this->repository->create('Question 3', 'Answer 3');

        $userId = 'user-123';
        $this->progressRepository->create(
            $flashcard1->id,
            $userId,
            Status::CORRECT->value
        );

        $this->progressRepository->create(
            $flashcard2->id,
            $userId,
            Status::INCORRECT->value
        );

        // Get practicable flashcards (should exclude flashcard1 since it's correct)
        $practicable = $this->repository->getPracticable($userId);

        $this->assertCount(2, $practicable);
        $this->assertTrue($practicable->contains('id', $flashcard2->id)); // incorrect - should be included
        $this->assertTrue($practicable->contains('id', $flashcard3->id)); // no progress - should be included
        $this->assertFalse($practicable->contains('id', $flashcard1->id)); // correct - should be excluded
    }
}
