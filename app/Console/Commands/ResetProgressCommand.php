<?php

namespace App\Console\Commands;

use App\Commands\ResetProgress;
use App\Console\Commands\Traits\FlashcardCommandHelpers;
use Illuminate\Console\Command;
use League\Tactician\CommandBus;

class ResetProgressCommand extends Command
{
    use FlashcardCommandHelpers;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flashcard:reset {--user-id= : The user ID to reset progress for} {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all practice progress while keeping flashcards';

    private CommandBus $commandBus;

    public function __construct(CommandBus $commandBus)
    {
        parent::__construct();
        $this->commandBus = $commandBus;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $userId = $this->option('user-id');
        
        if (!$userId) {
            $this->error('User ID is required. Please provide --user-id option.');
            return;
        }

        $this->resetUserProgress($userId);
    }

    /**
     * Reset progress for the given user
     */
    private function resetUserProgress(string $userId): void
    {
        $this->warn('⚠️  This will reset all practice progress but keep your flashcards.');

        if (!$this->option('force') && !$this->confirm('Are you sure you want to reset all progress?')) {
            $this->info('Reset cancelled.');
            return;
        }

        $result = $this->commandBus->handle(new ResetProgress($userId));

        $this->info("✅ Progress reset successfully!");
    }
} 