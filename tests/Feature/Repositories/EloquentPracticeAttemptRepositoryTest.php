<?php

namespace Feature\Repositories;

use App\Models\Flashcard;
use App\Models\PracticeAttempt;
use App\Repositories\EloquentPracticeAttemptRepository;
use App\Repositories\PracticeAttemptRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentPracticeAttemptRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PracticeAttemptRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentPracticeAttemptRepository();
    }

    public function test_can_count_by_user_id(): void
    {
        $flashcard1 = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);
        $flashcard2 = Flashcard::create(['question' => 'Q2', 'answer' => 'A2']);

        // Create attempts for user-1
        PracticeAttempt::create([
            'flashcard_id' => $flashcard1->id,
            'user_id' => 'user-1',
            'user_answer' => 'A1',
            'is_correct' => true,
        ]);

        PracticeAttempt::create([
            'flashcard_id' => $flashcard2->id,
            'user_id' => 'user-1',
            'user_answer' => 'Wrong',
            'is_correct' => false,
        ]);

        // Create attempt for user-2
        PracticeAttempt::create([
            'flashcard_id' => $flashcard1->id,
            'user_id' => 'user-2',
            'user_answer' => 'A1',
            'is_correct' => true,
        ]);

        // Assert
        $count = $this->repository->countByUserId('user-1');
        $this->assertEquals(2, $count);

        $count = $this->repository->countByUserId('user-2');
        $this->assertEquals(1, $count);

        $count = $this->repository->countByUserId('nonexistent-user');
        $this->assertEquals(0, $count);
    }

    public function test_can_delete_by_user_id(): void
    {
        $flashcard1 = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);
        $flashcard2 = Flashcard::create(['question' => 'Q2', 'answer' => 'A2']);

        // Create attempts for user-1
        PracticeAttempt::create([
            'flashcard_id' => $flashcard1->id,
            'user_id' => 'user-1',
            'user_answer' => 'A1',
            'is_correct' => true,
        ]);

        PracticeAttempt::create([
            'flashcard_id' => $flashcard2->id,
            'user_id' => 'user-1',
            'user_answer' => 'Wrong',
            'is_correct' => false,
        ]);

        // Create attempt for user-2
        PracticeAttempt::create([
            'flashcard_id' => $flashcard1->id,
            'user_id' => 'user-2',
            'user_answer' => 'A1',
            'is_correct' => true,
        ]);

        // Verify initial state
        $this->assertDatabaseCount('practice_attempts', 3);

        // Act
        $deletedCount = $this->repository->deleteByUserId('user-1');

        // Assert
        $this->assertEquals(2, $deletedCount);
        $this->assertDatabaseCount('practice_attempts', 1);

        // Verify only user-2's attempt remains
        $this->assertDatabaseHas('practice_attempts', [
            'user_id' => 'user-2',
        ]);

        $this->assertDatabaseMissing('practice_attempts', [
            'user_id' => 'user-1',
        ]);
    }

    public function test_delete_by_user_id_returns_zero_for_nonexistent_user(): void
    {
        $flashcard = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);

        PracticeAttempt::create([
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-1',
            'user_answer' => 'A1',
            'is_correct' => true,
        ]);

        $deletedCount = $this->repository->deleteByUserId('nonexistent-user');

        $this->assertEquals(0, $deletedCount);
        $this->assertDatabaseCount('practice_attempts', 1);
    }

    public function test_count_and_delete_operations_are_consistent(): void
    {
        $flashcard = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);

        // Create multiple attempts for the same user
        for ($i = 0; $i < 5; $i++) {
            PracticeAttempt::create([
                'flashcard_id' => $flashcard->id,
                'user_id' => 'test-user',
                'user_answer' => "Answer $i",
                'is_correct' => $i % 2 === 0,
            ]);
        }

        // Count before deletion
        $countBefore = $this->repository->countByUserId('test-user');
        $this->assertEquals(5, $countBefore);

        // Delete and verify count matches deleted count
        $deletedCount = $this->repository->deleteByUserId('test-user');
        $this->assertEquals($countBefore, $deletedCount);

        // Count after deletion should be zero
        $countAfter = $this->repository->countByUserId('test-user');
        $this->assertEquals(0, $countAfter);
    }

    public function test_can_create_practice_attempt(): void
    {
        $flashcard = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);

        $attempt = $this->repository->create($flashcard->id, 'user-123', 'A1', true);

        $this->assertInstanceOf(PracticeAttempt::class, $attempt);
        $this->assertEquals($flashcard->id, $attempt->flashcard_id);
        $this->assertEquals('user-123', $attempt->user_id);
        $this->assertEquals('A1', $attempt->user_answer);
        $this->assertTrue($attempt->is_correct);
        $this->assertNotNull($attempt->id);
        $this->assertNotNull($attempt->created_at);

        // Verify it's actually in the database
        $this->assertDatabaseHas('practice_attempts', [
            'id' => $attempt->id,
            'flashcard_id' => $flashcard->id,
            'user_id' => 'user-123',
            'user_answer' => 'A1',
            'is_correct' => true,
        ]);
    }

    public function test_can_create_multiple_attempts(): void
    {
        $flashcard = Flashcard::create(['question' => 'Q1', 'answer' => 'A1']);

        $attempt1 = $this->repository->create($flashcard->id, 'user-1', 'A1', true);
        $attempt2 = $this->repository->create($flashcard->id, 'user-2', 'Wrong', false);

        $this->assertNotEquals($attempt1->id, $attempt2->id);
        $this->assertTrue($attempt1->is_correct);
        $this->assertFalse($attempt2->is_correct);

        $this->assertDatabaseCount('practice_attempts', 2);
    }
} 