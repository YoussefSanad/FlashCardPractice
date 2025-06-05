<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Exception;

class DatabaseConnectionTest extends TestCase
{
    /**
     * Test that we can connect to the database.
     */
    public function test_database_connection_is_successful(): void
    {
        try {
            // Attempt to get the database connection
            $connection = DB::connection();
            
            // Test the connection by running a simple query
            $result = $connection->select('SELECT 1 as test');
            
            // Assert that we got a result
            $this->assertNotEmpty($result);
            $this->assertEquals(1, $result[0]->test);
        } catch (Exception $e) {
            $this->fail('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Test that we can retrieve database configuration.
     */
    public function test_database_configuration_is_accessible(): void
    {
        $defaultConnection = config('database.default');
        $this->assertNotEmpty($defaultConnection);
        
        $connectionConfig = config("database.connections.{$defaultConnection}");
        $this->assertNotEmpty($connectionConfig);
        $this->assertArrayHasKey('driver', $connectionConfig);
    }

    /**
     * Test that we can perform basic database operations.
     */
    public function test_basic_database_operations(): void
    {
        try {
            // Test creating a temporary table
            Schema::create('test_connection_table', function ($table) {
                $table->id();
                $table->string('test_column');
                $table->timestamps();
            });

            // Test inserting data
            DB::table('test_connection_table')->insert([
                'test_column' => 'test_value',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Test retrieving data
            $result = DB::table('test_connection_table')->where('test_column', 'test_value')->first();
            $this->assertNotNull($result);
            $this->assertEquals('test_value', $result->test_column);

            // Clean up - drop the test table
            Schema::dropIfExists('test_connection_table');
            
        } catch (Exception $e) {
            // Clean up in case of failure
            Schema::dropIfExists('test_connection_table');
            $this->fail('Database operations failed: ' . $e->getMessage());
        }
    }

    /**
     * Test that migrations table exists (indicates database is properly set up).
     */
    public function test_migrations_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('migrations'),
            'Migrations table does not exist. Have you run php artisan migrate?'
        );
    }

    /**
     * Test database transaction capabilities.
     */
    public function test_database_transactions(): void
    {
        try {
            DB::beginTransaction();
            
            // Create a test table for transaction testing
            Schema::create('transaction_test_table', function ($table) {
                $table->id();
                $table->string('name');
            });

            // Insert test data within transaction
            DB::table('transaction_test_table')->insert(['name' => 'test_transaction']);
            
            // Verify data exists within transaction
            $count = DB::table('transaction_test_table')->count();
            $this->assertEquals(1, $count);
            
            // Rollback transaction
            DB::rollBack();
            
            // Clean up
            Schema::dropIfExists('transaction_test_table');
            
        } catch (Exception $e) {
            DB::rollBack();
            Schema::dropIfExists('transaction_test_table');
            $this->fail('Database transaction test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test database connection with specific timeout.
     */
    public function test_database_connection_timeout(): void
    {
        $startTime = microtime(true);
        
        try {
            // Simple query to test connection speed
            DB::select('SELECT 1');
            $endTime = microtime(true);
            
            // Connection should be reasonably fast (less than 5 seconds)
            $this->assertLessThan(5, ($endTime - $startTime), 'Database connection is too slow');
            
        } catch (Exception $e) {
            $this->fail('Database connection timeout test failed: ' . $e->getMessage());
        }
    }
} 