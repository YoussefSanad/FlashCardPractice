<?php

namespace Integration\Repositories;

use App\Models\Flashcard;
use App\Models\PracticeStatus;
use App\Repositories\EloquentPracticeStatusRepository;
use App\Repositories\PracticeStatusRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentPracticeStatusRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PracticeStatusRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentPracticeStatusRepository();
    }

    public function test_can_create_practice_statuses(): void
    {
        $flashcard = Flashcard::create([
            'question' => 'Test Question',
            'answer' => 'Test Answer',
        ]);

        $flashcardId = $flashcard->id;
        $userId = 'user-123';
        $status = PracticeStatus::STATUS_NOT_ANSWERED;

        $progress = $this->repository->create($flashcardId, $userId, status: $status);

        $this->assertInstanceOf(PracticeStatus::class, $progress);
        $this->assertEquals($flashcardId, $progress->flashcard_id);
        $this->assertEquals($userId, $progress->user_id);
        $this->assertEquals($status, $progress->status);
        $this->assertNotNull($progress->id);
        $this->assertNotNull($progress->created_at);
        $this->assertNotNull($progress->updated_at);
        $this->assertNull($progress->last_attempted_at);

        // Verify it's actually in the database
        $this->assertDatabaseHas('practice_statuses', [
            'id' => $progress->id,
            'flashcard_id' => $flashcardId,
            'user_id' => $userId,
            'status' => $status,
        ]);
    }

    public function test_can_create_progress_with_different_statuses(): void
    {
        $flashcard1 = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);
        $flashcard2 = Flashcard::create(['question' => 'Q2', 'answer' => 'A2']);
        $flashcard3 = Flashcard::create(['question' => 'Q3', 'answer' => 'A3']);

        $progress1 = $this->repository->create($flashcard1->id, 'user-1', PracticeStatus::STATUS_NOT_ANSWERED);
        $progress2 = $this->repository->create($flashcard2->id, 'user-1', PracticeStatus::STATUS_CORRECT);
        $progress3 = $this->repository->create($flashcard3->id, 'user-1', PracticeStatus::STATUS_INCORRECT);

        $this->assertEquals(PracticeStatus::STATUS_NOT_ANSWERED, $progress1->status);
        $this->assertEquals(PracticeStatus::STATUS_CORRECT, $progress2->status);
        $this->assertEquals(PracticeStatus::STATUS_INCORRECT, $progress3->status);

        $this->assertDatabaseCount('practice_statuses', 3);
    }

    public function test_can_create_progress_for_multiple_users(): void
    {
        $flashcard = Flashcard::create(['question' => 'Shared Question', 'answer' => 'Shared Answer']);

        $progress1 = $this->repository->create($flashcard->id, 'user-1', PracticeStatus::STATUS_CORRECT);
        $progress2 = $this->repository->create($flashcard->id, 'user-2', PracticeStatus::STATUS_INCORRECT);

        $this->assertEquals('user-1', $progress1->user_id);
        $this->assertEquals('user-2', $progress2->user_id);
        $this->assertEquals($flashcard->id, $progress1->flashcard_id);
        $this->assertEquals($flashcard->id, $progress2->flashcard_id);

        $this->assertDatabaseCount('practice_statuses', 2);
    }

    public function test_can_create_progress_for_different_flashcards(): void
    {
        $flashcard1 = Flashcard::create(['question' => 'Question 1', 'answer' => 'Answer 1']);
        $flashcard2 = Flashcard::create(['question' => 'Question 2', 'answer' => 'Answer 2']);

        $progress1 = $this->repository->create($flashcard1->id, 'user-1', PracticeStatus::STATUS_CORRECT);
        $progress2 = $this->repository->create($flashcard2->id, 'user-1', PracticeStatus::STATUS_INCORRECT);

        $this->assertNotEquals($progress1->flashcard_id, $progress2->flashcard_id);
        $this->assertEquals($flashcard1->id, $progress1->flashcard_id);
        $this->assertEquals($flashcard2->id, $progress2->flashcard_id);
    }

    public function test_repository_respects_database_constraints(): void
    {
        $flashcard = Flashcard::create(['question' => 'Test Question', 'answer' => 'Test Answer']);

        // Create first progress record
        $this->repository->create($flashcard->id, 'user-123', PracticeStatus::STATUS_NOT_ANSWERED);

        // Try to create duplicate progress for same user and flashcard (should fail due to unique constraint)
        $this->expectException(QueryException::class);
        $this->repository->create($flashcard->id, 'user-123', PracticeStatus::STATUS_CORRECT);
    }

    public function test_foreign_key_constraint_prevents_invalid_flashcard_id(): void
    {
        $nonExistentFlashcardId = 99999;

        $this->expectException(QueryException::class);
        $this->repository->create($nonExistentFlashcardId, 'user-123', PracticeStatus::STATUS_NOT_ANSWERED);
    }

    public function test_progress_belongs_to_flashcard(): void
    {
        $flashcard = Flashcard::create(['question' => 'Test Question', 'answer' => 'Test Answer']);
        $progress = $this->repository->create($flashcard->id, 'user-123', PracticeStatus::STATUS_NOT_ANSWERED);

        // Test the relationship
        $this->assertNotNull($progress->flashcard);
        $this->assertEquals($flashcard->id, $progress->flashcard->id);
        $this->assertEquals('Test Question', $progress->flashcard->question);
    }

    public function test_can_count_non_not_answered_by_user_id(): void
    {
        $flashcard1 = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);
        $flashcard2 = Flashcard::create(['question' => 'Q2', 'answer' => 'A2']);
        $flashcard3 = Flashcard::create(['question' => 'Q3', 'answer' => 'A3']);
        $flashcard4 = Flashcard::create(['question' => 'Q4', 'answer' => 'A4']);

        // Create progress for user-1
        $this->repository->create($flashcard1->id, 'user-1', PracticeStatus::STATUS_NOT_ANSWERED);
        $this->repository->create($flashcard2->id, 'user-1', PracticeStatus::STATUS_CORRECT);
        $this->repository->create($flashcard3->id, 'user-1', PracticeStatus::STATUS_INCORRECT);

        // Create progress for user-2
        $this->repository->create($flashcard4->id, 'user-2', PracticeStatus::STATUS_CORRECT);

        // Assert
        $count = $this->repository->countAttemptedFor('user-1');
        $this->assertEquals(2, $count); // Only CORRECT and INCORRECT, not NOT_ANSWERED

        $count = $this->repository->countAttemptedFor('user-2');
        $this->assertEquals(1, $count);

        $count = $this->repository->countAttemptedFor('nonexistent-user');
        $this->assertEquals(0, $count);
    }

    public function test_can_reset_progress_by_user_id(): void
    {
        $flashcard1 = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);
        $flashcard2 = Flashcard::create(['question' => 'Q2', 'answer' => 'A2']);
        $flashcard3 = Flashcard::create(['question' => 'Q3', 'answer' => 'A3']);

        // Create progress for user-1
        PracticeStatus::create([
            'flashcard_id' => $flashcard1->id,
            'user_id' => 'user-1',
            'status' => PracticeStatus::STATUS_CORRECT,
            'last_attempted_at' => now(),
        ]);

        PracticeStatus::create([
            'flashcard_id' => $flashcard2->id,
            'user_id' => 'user-1',
            'status' => PracticeStatus::STATUS_INCORRECT,
            'last_attempted_at' => now(),
        ]);

        // Create progress for user-2 (should not be affected)
        PracticeStatus::create([
            'flashcard_id' => $flashcard3->id,
            'user_id' => 'user-2',
            'status' => PracticeStatus::STATUS_CORRECT,
            'last_attempted_at' => now(),
        ]);

        // Act
        $updatedCount = $this->repository->resetFor('user-1');

        // Assert
        $this->assertEquals(2, $updatedCount);

        // Verify user-1's progress is reset
        $progress1 = PracticeStatus::where('flashcard_id', $flashcard1->id)->where('user_id', 'user-1')->first();
        $progress2 = PracticeStatus::where('flashcard_id', $flashcard2->id)->where('user_id', 'user-1')->first();

        $this->assertEquals(PracticeStatus::STATUS_NOT_ANSWERED, $progress1->status);
        $this->assertNull($progress1->last_attempted_at);
        $this->assertEquals(PracticeStatus::STATUS_NOT_ANSWERED, $progress2->status);
        $this->assertNull($progress2->last_attempted_at);

        // Verify user-2's progress is unchanged
        $progress3 = PracticeStatus::where('flashcard_id', $flashcard3->id)->where('user_id', 'user-2')->first();
        $this->assertEquals(PracticeStatus::STATUS_CORRECT, $progress3->status);
        $this->assertNotNull($progress3->last_attempted_at);
    }

    public function test_reset_progress_returns_zero_for_nonexistent_user(): void
    {
        $flashcard = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);
        $this->repository->create($flashcard->id, 'user-1', PracticeStatus::STATUS_CORRECT);

        $updatedCount = $this->repository->resetFor('nonexistent-user');

        $this->assertEquals(0, $updatedCount);
    }

    public function test_can_find_by_flashcard_and_user(): void
    {
        $flashcard1 = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);
        $flashcard2 = Flashcard::create(['question' => 'Q2', 'answer' => 'A2']);

        $progress1 = $this->repository->create($flashcard1->id, 'user-1', PracticeStatus::STATUS_CORRECT);
        $progress2 = $this->repository->create($flashcard2->id, 'user-1', PracticeStatus::STATUS_INCORRECT);
        $progress3 = $this->repository->create($flashcard1->id, 'user-2', PracticeStatus::STATUS_NOT_ANSWERED);

        // Test finding existing progress
        $found = $this->repository->findBy($flashcard1->id, 'user-1');
        $this->assertNotNull($found);
        $this->assertEquals($progress1->id, $found->id);
        $this->assertEquals(PracticeStatus::STATUS_CORRECT, $found->status);

        // Test finding different user
        $found = $this->repository->findBy($flashcard1->id, 'user-2');
        $this->assertNotNull($found);
        $this->assertEquals($progress3->id, $found->id);

        // Test finding non-existent combination
        $found = $this->repository->findBy($flashcard2->id, 'user-2');
        $this->assertNull($found);
    }

    public function test_can_update_progress(): void
    {
        $flashcard = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);
        $progress = $this->repository->create($flashcard->id, 'user-1', PracticeStatus::STATUS_NOT_ANSWERED);

        $lastAttemptedAt = new \DateTimeImmutable();
        $updatedProgress = $this->repository->updateStatus($progress, PracticeStatus::STATUS_CORRECT, $lastAttemptedAt);

        $this->assertEquals($progress->id, $updatedProgress->id);
        $this->assertEquals(PracticeStatus::STATUS_CORRECT, $updatedProgress->status);
        $this->assertNotNull($updatedProgress->last_attempted_at);

        // Verify it's updated in the database
        $this->assertDatabaseHas('practice_statuses', [
            'id' => $progress->id,
            'status' => PracticeStatus::STATUS_CORRECT,
        ]);
    }

    public function test_can_update_progress_without_last_attempted_at(): void
    {
        $flashcard = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);
        $progress = $this->repository->create($flashcard->id, 'user-1', PracticeStatus::STATUS_CORRECT, new \DateTimeImmutable());

        $updatedProgress = $this->repository->updateStatus($progress, PracticeStatus::STATUS_INCORRECT);

        $this->assertEquals(PracticeStatus::STATUS_INCORRECT, $updatedProgress->status);
        $this->assertNull($updatedProgress->last_attempted_at);
    }
}
