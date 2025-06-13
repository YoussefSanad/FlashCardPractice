<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FlashcardCommandHelpers;
use App\Queries\GetStats;
use Illuminate\Console\Command;
use League\Tactician\CommandBus;

class ShowStatsCommand extends Command
{
    use FlashcardCommandHelpers;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flashcard:stats {--user-id= : The user ID to show stats for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show flashcard practice statistics';

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

        $this->displayStats($userId);
    }

    /**
     * Display statistics for the given user
     */
    private function displayStats(string $userId): void
    {
        $this->info('📈 Practice Statistics');
        $this->newLine();

        $stats = $this->commandBus->handle(new GetStats($userId));

        if ($stats['total_questions'] === 0) {
            $this->warn('No flashcards found. Create some flashcards first!');
            return;
        }

        $this->line("Total amount of questions: {$stats['total_questions']}");
        $this->line("% of questions that have an answer (attempted): {$stats['attempted_percentage']}%");
        $this->line("% of questions that have a correct answer: {$stats['correct_percentage']}%");
        $this->newLine();
        $this->line("Attempted questions: {$stats['attempted_count']}/{$stats['total_questions']}");
        $this->line("Correct answers: {$stats['correct_count']}/{$stats['total_questions']}");
    }
} 