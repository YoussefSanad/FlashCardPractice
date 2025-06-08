<?php

namespace Integration\Middleware;

use App\Commands\CreateFlashcard;
use App\Queries\GetAllFlashcards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use League\Tactician\CommandBus;
use Tests\TestCase;

class TransactionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_wraps_commands_requiring_transaction_in_database_transaction(): void
    {
        // Arrange
        $commandBus = $this->app->make(CommandBus::class);
        $command = new CreateFlashcard(
            'Transaction Test Question',
            'Transaction Test Answer',
            'transaction-user'
        );

        // Track if we're in a transaction during execution
        $wasInTransaction = false;
        
        // Use a database event to check if we're in a transaction
        DB::listen(function () use (&$wasInTransaction) {
            $wasInTransaction = DB::transactionLevel() > 0;
        });

        // Act
        $result = $commandBus->handle($command);

        // Assert
        $this->assertTrue($wasInTransaction, 'Command should have been executed within a database transaction');
        $this->assertNotNull($result);
        $this->assertEquals('Transaction Test Question', $result->question);
    }

    public function test_does_not_wrap_queries_in_database_transaction(): void
    {
        // Arrange - First create a flashcard to query
        $commandBus = $this->app->make(CommandBus::class);
        $createCommand = new CreateFlashcard(
            'Query Test Question',
            'Query Test Answer',
            'query-user'
        );
        $commandBus->handle($createCommand);

        // Now test the query
        $query = new GetAllFlashcards('query-user');
        
        // Track if we're in a transaction during query execution
        $wasInTransaction = false;
        
        DB::listen(function () use (&$wasInTransaction) {
            $wasInTransaction = DB::transactionLevel() > 0;
        });

        // Act
        $result = $commandBus->handle($query);

        // Assert
        $this->assertFalse($wasInTransaction, 'Query should NOT have been executed within a database transaction');
        $this->assertNotEmpty($result);
        $this->assertEquals(1, $result->count());
    }

    public function test_transaction_rollback_on_command_failure(): void
    {
        // Arrange
        $commandBus = $this->app->make(CommandBus::class);
        $invalidCommand = new CreateFlashcard(
            '', // Empty question will cause validation failure
            'Test Answer',
            'rollback-user'
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);

        try {
            $commandBus->handle($invalidCommand);
        } catch (\InvalidArgumentException $e) {
            // Verify no records were created due to transaction rollback
            $this->assertDatabaseCount('flashcards', 0);
            $this->assertDatabaseCount('practice_statuses', 0);
            
            throw $e; // Re-throw to satisfy expectException
        }
    }
} 