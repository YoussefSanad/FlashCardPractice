<?php

namespace Integration\Middleware;

use App\Commands\CreateFlashcard;
use App\Models\Flashcard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use League\Tactician\CommandBus;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();
    }

    public function test_executes_command_first_time(): void
    {
        $commandBus = $this->app->make(CommandBus::class);

        $command = new CreateFlashcard(
            'What is idempotency?',
            'The ability to apply an operation multiple times without changing the result'
        );

        $result = $commandBus->handle($command);

        $this->assertInstanceOf(Flashcard::class, $result);
        $this->assertEquals('What is idempotency?', $result->question);
        $this->assertEquals('The ability to apply an operation multiple times without changing the result', $result->answer);

        // Verify it was actually saved to database
        $this->assertDatabaseHas('flashcards', [
            'id' => $result->id,
            'question' => 'What is idempotency?',
        ]);
    }

    public function test_returns_cached_result_for_duplicate_command(): void
    {
        $commandBus = $this->app->make(CommandBus::class);

        $command = new CreateFlashcard(
            'What is idempotency?',
            'The ability to apply an operation multiple times without changing the result',
        );

        // Execute first time
        $firstResult = $commandBus->handle($command);
        $firstId = $firstResult->id;

        // Execute the exact same command again
        $secondResult = $commandBus->handle($command);

        // Should return the same result (cached)
        $this->assertEquals($firstId, $secondResult->id);
        $this->assertEquals($firstResult->question, $secondResult->question);
        $this->assertEquals($firstResult->answer, $secondResult->answer);

        // Should only have one record in database (not duplicated)
        $this->assertDatabaseCount('flashcards', 1);

        // Verify cache contains the result
        $this->assertTrue(Cache::has('idempotency:' . $this->generateExpectedKey($command)));
    }

    public function test_different_commands_execute_separately(): void
    {
        $commandBus = $this->app->make(CommandBus::class);

        $command1 = new CreateFlashcard(
            'Question 1',
            'Answer 1',
        );

        $command2 = new CreateFlashcard(
            'Question 2', // Different question
            'Answer 2',
            'user-1'
        );

        $result1 = $commandBus->handle($command1);
        $result2 = $commandBus->handle($command2);

        // Different commands should produce different results
        $this->assertNotEquals($result1->id, $result2->id);
        $this->assertEquals('Question 1', $result1->question);
        $this->assertEquals('Question 2', $result2->question);

        // Should have two records in database
        $this->assertDatabaseCount('flashcards', 2);
    }

    public function test_idempotency_key_generation_is_consistent(): void
    {
        $command1 = new CreateFlashcard('Test', 'Test', 'user-1');
        $command2 = new CreateFlashcard('Test', 'Test', 'user-1');

        $key1 = $this->generateExpectedKey($command1);
        $key2 = $this->generateExpectedKey($command2);

        $this->assertEquals($key1, $key2, 'Identical commands should generate the same idempotency key');
    }

    public function test_idempotency_key_generation_is_unique_for_different_commands(): void
    {
        $command1 = new CreateFlashcard('Test 1', 'Test', 'user-1');
        $command2 = new CreateFlashcard('Test 2', 'Test', 'user-1');

        $key1 = $this->generateExpectedKey($command1);
        $key2 = $this->generateExpectedKey($command2);

        $this->assertNotEquals($key1, $key2, 'Different commands should generate different idempotency keys');
    }

    public function test_cache_expiration_allows_re_execution(): void
    {
        $commandBus = $this->app->make(CommandBus::class);

        $command = new CreateFlashcard(
            'Cache Test',
            'Test Answer',
            'user-cache'
        );

        // Execute first time
        $firstResult = $commandBus->handle($command);

        // Manually expire the cache
        $cacheKey = 'idempotency:' . $this->generateExpectedKey($command);
        Cache::forget($cacheKey);

        // Execute again after cache expiry
        $secondResult = $commandBus->handle($command);

        // Should create a new record since cache expired
        $this->assertNotEquals($firstResult->id, $secondResult->id);

        // Should have two records in database
        $this->assertDatabaseCount('flashcards', 2);
    }


    public function test_cached_result_preserves_model_state(): void
    {
        $commandBus = $this->app->make(CommandBus::class);

        $command = new CreateFlashcard(
            'Model State Test',
            'Test Answer',
            'user-state'
        );

        $firstResult = $commandBus->handle($command);
        $secondResult = $commandBus->handle($command); // Should come from cache

        // Verify the cached model behaves like the original
        $this->assertInstanceOf(Flashcard::class, $secondResult);
        $this->assertTrue($secondResult->exists, message: 'Cached model should be marked as existing');
        $this->assertEquals($firstResult->id, $secondResult->id);
        $this->assertEquals($firstResult->question, $secondResult->question);
        $this->assertEquals($firstResult->answer, $secondResult->answer);
    }

    /**
     * Helper method to generate the expected idempotency key for testing
     */
    private function generateExpectedKey($command): string
    {
        // Match the simplified approach used in the middleware
        $commandClass = get_class($command);
        $serializedCommand = serialize($command);

        return hash('sha256', $commandClass . ':' . $serializedCommand);
    }
}
