<?php

namespace Integration\Middleware;

use App\Commands\CreateFlashcard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use League\Tactician\CommandBus;
use Tests\TestCase;

class LoggingMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_successful_command_execution(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Command execution started', \Mockery::on(function ($context) {
                return $context['command'] === 'App\Commands\CreateFlashcard' &&
                       isset($context['data']) &&
                       $context['data']['question'] === 'Test Question' &&
                       $context['data']['answer'] === 'Test Answer';
            }));

        Log::shouldReceive('info')
            ->once()
            ->with('Command executed successfully', \Mockery::on(function ($context) {
                return $context['command'] === 'App\Commands\CreateFlashcard' &&
                       isset($context['execution_time_ms']) &&
                       $context['execution_time_ms'] >= 0 &&
                       $context['result_type'] === 'App\Models\Flashcard';
            }));

        $commandBus = $this->app->make(CommandBus::class);

        $command = new CreateFlashcard(
            'Test Question',
            'Test Answer',
        );

        $result = $commandBus->handle($command);

        $this->assertNotNull($result);
    }

    public function test_logs_failed_command_execution(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Command execution started', \Mockery::on(function ($context) {
                return $context['command'] === 'App\Commands\CreateFlashcard' &&
                       isset($context['data']) &&
                       $context['data']['question'] === '' &&
                       $context['data']['answer'] === 'Test Answer';
            }));

        Log::shouldReceive('error')
            ->once()
            ->with('Command execution failed', \Mockery::on(function ($context) {
                return $context['command'] === 'App\Commands\CreateFlashcard' &&
                       isset($context['execution_time_ms']) &&
                       $context['execution_time_ms'] >= 0 &&
                       $context['exception'] === 'InvalidArgumentException' &&
                       $context['message'] === 'Question cannot be empty.';
            }));

        $commandBus = $this->app->make(CommandBus::class);

        $command = new CreateFlashcard(
            '', // Empty question will cause validation failure
            'Test Answer',
        );

        $this->expectException(\InvalidArgumentException::class);
        $commandBus->handle($command);
    }

    public function test_truncates_long_strings_in_command_data(): void
    {
        $longQuestion = str_repeat('A very long question ', 10); // Over 100 characters

        Log::shouldReceive('info')
            ->once()
            ->with('Command execution started', \Mockery::on(function ($context) use ($longQuestion) {
                $expectedTruncated = substr($longQuestion, 0, 100) . '...';
                return $context['data']['question'] === $expectedTruncated;
            }));

        Log::shouldReceive('info')
            ->once()
            ->with('Command executed successfully', \Mockery::any());

        $commandBus = $this->app->make(CommandBus::class);

        $command = new CreateFlashcard(
            $longQuestion,
            'Test Answer',
        );

        $result = $commandBus->handle($command);

        $this->assertNotNull($result);
    }

    public function test_logs_execution_timing(): void
    {
        $startTime = microtime(true);

        Log::shouldReceive('info')
            ->once()
            ->with('Command execution started', \Mockery::any());

        Log::shouldReceive('info')
            ->once()
            ->with('Command executed successfully', \Mockery::on(function ($context) use ($startTime) {
                $executionTime = $context['execution_time_ms'];
                // Execution should be reasonably fast (under 1 second = 1000ms)
                return is_numeric($executionTime) && $executionTime >= 0 && $executionTime < 1000;
            }));

        $commandBus = $this->app->make(CommandBus::class);

        $command = new CreateFlashcard(
            'Test Question',
            'Test Answer',
        );

        $result = $commandBus->handle($command);

        $this->assertNotNull($result);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
