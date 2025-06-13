<?php

namespace App\Console\Commands;

use App\Console\Commands\Traits\FlashcardCommandHelpers;
use App\Queries\GetAllFlashcards;
use Illuminate\Console\Command;
use League\Tactician\CommandBus;

class ListFlashcardsCommand extends Command
{
    use FlashcardCommandHelpers;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flashcard:list {--user-id= : The user ID to list flashcards for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all flashcards for the current user';

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

        $this->displayFlashcards($userId);
    }

    /**
     * Display all flashcards for the given user
     */
    private function displayFlashcards(string $userId): void
    {
        $this->info('📋 All Flashcards');
        $this->newLine();

        $flashcards = $this->commandBus->handle(new GetAllFlashcards($userId));

        if ($flashcards->isEmpty()) {
            $this->warn('No flashcards found. Create some flashcards first!');
            return;
        }

        $tableData = [];
        foreach ($flashcards as $flashcard) {
            $tableData[] = [
                'ID' => $flashcard->id,
                'Question' => $this->truncateText($flashcard->question, 50),
                'Answer' => $this->truncateText($flashcard->answer, 30),
            ];
        }

        $this->table(['ID', 'Question', 'Answer'], $tableData);
    }


} 