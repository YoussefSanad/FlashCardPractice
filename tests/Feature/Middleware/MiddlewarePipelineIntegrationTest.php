<?php

namespace Feature\Middleware;

use App\Commands\CreateFlashcard;
use App\Models\Flashcard;
use App\Models\QuestionProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use League\Tactician\CommandBus;
use Tests\TestCase;

class MiddlewarePipelineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_complete_middleware_pipeline_flow(): void
    {
        // Arrange
        $commandBus = $this->app->make(CommandBus::class);
        $command = new CreateFlashcard(
            'Integration Test Question',
            'Integration Test Answer',
            'pipeline-user'
        );

        // Act - First execution (full pipeline)
        $result1 = $commandBus->handle($command);

        // Assert - First execution results
        $this->assertInstanceOf(Flashcard::class, $result1);
        $this->assertEquals('Integration Test Question', $result1->question);
        $this->assertEquals('Integration Test Answer', $result1->answer);

        // Verify database persistence (TransactionMiddleware working)
        $this->assertDatabaseHas('flashcards', [
            'id' => $result1->id,
            'question' => 'Integration Test Question',
            'answer' => 'Integration Test Answer',
        ]);

        // Verify progress record created (Handler working)
        $this->assertDatabaseHas('question_progress', [
            'flashcard_id' => $result1->id,
            'user_id' => 'pipeline-user',
            'status' => 'not_answered',
        ]);

        // Act - Second execution (should hit idempotency cache)
        $startTime = microtime(true);
        $result2 = $commandBus->handle($command);
        $executionTime = (microtime(true) - $startTime) * 1000;

        // Assert - Idempotency working
        $this->assertEquals($result1->id, $result2->id);
        $this->assertEquals($result1->question, $result2->question);
        $this->assertEquals($result1->answer, $result2->answer);

        // Should be much faster (cached)
        $this->assertLessThan(5, $executionTime, 'Cached execution should be under 5ms');

        // Should not create duplicate records
        $this->assertDatabaseCount('flashcards', 1);
        $this->assertDatabaseCount('question_progress', 1);
    }

    public function test_middleware_pipeline_handles_failures_correctly(): void
    {
        // Arrange
        $commandBus = $this->app->make(CommandBus::class);
        $invalidCommand = new CreateFlashcard(
            '', // Invalid empty question
            'Test Answer',
            'error-user'
        );

        // Act & Assert - Should throw validation exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Question cannot be empty');

        try {
            $commandBus->handle($invalidCommand);
        } catch (\InvalidArgumentException $e) {
            // Verify transaction rollback - no records should be created
            $this->assertDatabaseCount('flashcards', 0);
            $this->assertDatabaseCount('question_progress', 0);

            // Re-throw to satisfy expectException
            throw $e;
        }
    }

    public function test_middleware_pipeline_with_different_commands(): void
    {
        // Arrange
        $commandBus = $this->app->make(CommandBus::class);

        $command1 = new CreateFlashcard('Question 1', 'Answer 1', 'user-1');
        $command2 = new CreateFlashcard('Question 2', 'Answer 2', 'user-2');
        $command3 = new CreateFlashcard('Question 1', 'Answer 1', 'user-1'); // Duplicate of command1

        // Act
        $result1 = $commandBus->handle($command1);
        $result2 = $commandBus->handle($command2);
        $result3 = $commandBus->handle($command3); // Should be cached

        // Assert
        // Command 1 and 2 are different - should create separate records
        $this->assertNotEquals($result1->id, $result2->id);

        // Command 3 is identical to command 1 - should return cached result
        $this->assertEquals($result1->id, $result3->id);

        // Should have 2 flashcards and 2 progress records (not 3)
        $this->assertDatabaseCount('flashcards', 2);
        $this->assertDatabaseCount('question_progress', 2);
    }

    public function test_middleware_pipeline_preserves_model_relationships(): void
    {
        // Arrange
        $commandBus = $this->app->make(CommandBus::class);
        $command = new CreateFlashcard(
            'Relationship Test',
            'Test Answer',
            'relationship-user'
        );

        // Act
        $flashcard = $commandBus->handle($command);

        // Assert - Verify relationships work through the pipeline
        $this->assertInstanceOf(Flashcard::class, $flashcard);

        // Load the progress relationship
        $flashcard->load('questionProgress');
        $this->assertTrue($flashcard->questionProgress->isNotEmpty());

        $progress = $flashcard->questionProgress->first();
        $this->assertInstanceOf(QuestionProgress::class, $progress);
        $this->assertEquals('relationship-user', $progress->user_id);
        $this->assertEquals('not_answered', $progress->status);
        $this->assertEquals($flashcard->id, $progress->flashcard_id);
    }

    public function test_middleware_pipeline_caching_with_complex_data(): void
    {
        // Arrange
        $commandBus = $this->app->make(CommandBus::class);
        $complexCommand = new CreateFlashcard(
            'Complex Question with "quotes" and special chars: àéîôù & symbols!',
            'Complex Answer with newlines\nand\ttabs\tand émojis 🚀',
            'complex-user-123'
        );

        // Act
        $result1 = $commandBus->handle($complexCommand);
        $result2 = $commandBus->handle($complexCommand); // Should be cached

        // Assert
        $this->assertEquals($result1->id, $result2->id);
        $this->assertEquals($result1->question, $result2->question);
        $this->assertEquals($result1->answer, $result2->answer);

        // Verify special characters are preserved
        $this->assertStringContainsString('"quotes"', $result2->question);
        $this->assertStringContainsString('àéîôù', $result2->question);
        $this->assertStringContainsString('\n', $result2->answer);
        $this->assertStringContainsString('🚀', $result2->answer);

        // Should only create one record despite complex data
        $this->assertDatabaseCount('flashcards', 1);
    }
}
