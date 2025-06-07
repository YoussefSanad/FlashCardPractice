<?php

namespace Tests\Unit\Queries;

use App\Queries\GetStats;
use App\Queries\GetStatsHandler;
use App\Models\PracticeStatus;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Repositories\InMemoryFlashcardRepository;
use Tests\Unit\Repositories\InMemoryPracticeStatusRepository;

class GetStatsHandlerTest extends TestCase
{
    private InMemoryFlashcardRepository $flashcardRepository;
    private InMemoryPracticeStatusRepository $practiceStatusRepository;
    private GetStatsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flashcardRepository = new InMemoryFlashcardRepository();
        $this->practiceStatusRepository = new InMemoryPracticeStatusRepository();
        $this->handler = new GetStatsHandler(
            $this->flashcardRepository,
            $this->practiceStatusRepository
        );
    }

    public function test_returns_zero_stats_when_no_flashcards_exist(): void
    {
        $query = new GetStats('user-123');

        $stats = $this->handler->handle($query);

        $this->assertEquals([
            'total_questions' => 0,
            'attempted_percentage' => 0,
            'correct_percentage' => 0,
            'attempted_count' => 0,
            'correct_count' => 0,
        ], $stats);
    }

    public function test_returns_correct_stats_with_no_attempts(): void
    {
        // Arrange - Create flashcards but no attempts
        $this->flashcardRepository->create('Question 1', 'Answer 1');
        $this->flashcardRepository->create('Question 2', 'Answer 2');
        $this->flashcardRepository->create('Question 3', 'Answer 3');

        $query = new GetStats('user-123');

        // Act
        $stats = $this->handler->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 3,
            'attempted_percentage' => 0.0,
            'correct_percentage' => 0.0,
            'attempted_count' => 0,
            'correct_count' => 0,
        ], $stats);
    }

    public function test_returns_correct_stats_with_mixed_attempts(): void
    {
        // Arrange - Create flashcards
        $flashcard1 = $this->flashcardRepository->create('Question 1', 'Answer 1');
        $flashcard2 = $this->flashcardRepository->create('Question 2', 'Answer 2');
        $flashcard3 = $this->flashcardRepository->create('Question 3', 'Answer 3');
        $flashcard4 = $this->flashcardRepository->create('Question 4', 'Answer 4');

        // Create practice statuses - mixed results
        $this->practiceStatusRepository->create($flashcard1->id, 'user-123', PracticeStatus::STATUS_CORRECT);
        $this->practiceStatusRepository->create($flashcard2->id, 'user-123', PracticeStatus::STATUS_INCORRECT);
        $this->practiceStatusRepository->create($flashcard3->id, 'user-123', PracticeStatus::STATUS_CORRECT);
        // flashcard4 has no attempt (STATUS_NOT_ANSWERED)

        $query = new GetStats('user-123');

        // Act
        $stats = $this->handler->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 4,
            'attempted_percentage' => 75.0, // 3 out of 4 attempted
            'correct_percentage' => 50.0,   // 2 out of 4 correct
            'attempted_count' => 3,
            'correct_count' => 2,
        ], $stats);
    }

    public function test_returns_correct_stats_with_all_correct_attempts(): void
    {
        // Arrange
        $flashcard1 = $this->flashcardRepository->create('Question 1', 'Answer 1');
        $flashcard2 = $this->flashcardRepository->create('Question 2', 'Answer 2');

        $this->practiceStatusRepository->create($flashcard1->id, 'user-123', PracticeStatus::STATUS_CORRECT);
        $this->practiceStatusRepository->create($flashcard2->id, 'user-123', PracticeStatus::STATUS_CORRECT);

        $query = new GetStats('user-123');

        // Act
        $stats = $this->handler->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 2,
            'attempted_percentage' => 100.0,
            'correct_percentage' => 100.0,
            'attempted_count' => 2,
            'correct_count' => 2,
        ], $stats);
    }

    public function test_returns_correct_stats_with_all_incorrect_attempts(): void
    {
        // Arrange
        $flashcard1 = $this->flashcardRepository->create('Question 1', 'Answer 1');
        $flashcard2 = $this->flashcardRepository->create('Question 2', 'Answer 2');

        $this->practiceStatusRepository->create($flashcard1->id, 'user-123', PracticeStatus::STATUS_INCORRECT);
        $this->practiceStatusRepository->create($flashcard2->id, 'user-123', PracticeStatus::STATUS_INCORRECT);

        $query = new GetStats('user-123');

        // Act
        $stats = $this->handler->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 2,
            'attempted_percentage' => 100.0,
            'correct_percentage' => 0.0,
            'attempted_count' => 2,
            'correct_count' => 0,
        ], $stats);
    }

    public function test_isolates_stats_by_user_id(): void
    {
        // Arrange
        $flashcard1 = $this->flashcardRepository->create('Question 1', 'Answer 1');
        $flashcard2 = $this->flashcardRepository->create('Question 2', 'Answer 2');

        // User 1 has correct answers
        $this->practiceStatusRepository->create($flashcard1->id, 'user-1', PracticeStatus::STATUS_CORRECT);
        $this->practiceStatusRepository->create($flashcard2->id, 'user-1', PracticeStatus::STATUS_CORRECT);

        // User 2 has incorrect answers
        $this->practiceStatusRepository->create($flashcard1->id, 'user-2', PracticeStatus::STATUS_INCORRECT);
        $this->practiceStatusRepository->create($flashcard2->id, 'user-2', PracticeStatus::STATUS_INCORRECT);

        // Act
        $statsUser1 = $this->handler->handle(new GetStats('user-1'));
        $statsUser2 = $this->handler->handle(new GetStats('user-2'));

        // Assert
        $this->assertEquals(100.0, $statsUser1['correct_percentage']);
        $this->assertEquals(2, $statsUser1['correct_count']);

        $this->assertEquals(0.0, $statsUser2['correct_percentage']);
        $this->assertEquals(0, $statsUser2['correct_count']);
    }

    public function test_handles_decimal_percentages_correctly(): void
    {
        // Arrange - Create 3 flashcards for non-even percentages
        $flashcard1 = $this->flashcardRepository->create('Question 1', 'Answer 1');
        $flashcard2 = $this->flashcardRepository->create('Question 2', 'Answer 2');
        $flashcard3 = $this->flashcardRepository->create('Question 3', 'Answer 3');

        // Only one correct out of 3 total (33.3%)
        $this->practiceStatusRepository->create($flashcard1->id, 'user-123', PracticeStatus::STATUS_CORRECT);
        // No attempts for flashcard2 and flashcard3

        $query = new GetStats('user-123');

        // Act
        $stats = $this->handler->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 3,
            'attempted_percentage' => 33.3, // 1 out of 3, rounded to 1 decimal
            'correct_percentage' => 33.3,   // 1 out of 3, rounded to 1 decimal
            'attempted_count' => 1,
            'correct_count' => 1,
        ], $stats);
    }

    public function test_handles_user_with_no_progress_records(): void
    {
        // Arrange
        $this->flashcardRepository->create('Question 1', 'Answer 1');
        $this->flashcardRepository->create('Question 2', 'Answer 2');

        // Create progress for different user
        $flashcard = $this->flashcardRepository->getAll()[0];
        $this->practiceStatusRepository->create($flashcard->id, 'other-user', PracticeStatus::STATUS_CORRECT);

        $query = new GetStats('user-without-progress');

        // Act
        $stats = $this->handler->handle($query);

        // Assert
        $this->assertEquals([
            'total_questions' => 2,
            'attempted_percentage' => 0.0,
            'correct_percentage' => 0.0,
            'attempted_count' => 0,
            'correct_count' => 0,
        ], $stats);
    }
} 