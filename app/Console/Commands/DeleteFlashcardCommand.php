<?php

namespace App\Console\Commands;

use App\Commands\DeleteFlashcard;
use App\Console\Commands\Traits\FlashcardCommandHelpers;
use App\Queries\GetAllFlashcards;
use Illuminate\Console\Command;
use League\Tactician\CommandBus;

class DeleteFlashcardCommand extends Command
{
    use FlashcardCommandHelpers;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flashcard:delete {--user-id= : The user ID to delete flashcard for} {--id= : The flashcard ID to delete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a flashcard';

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

        $flashcardId = $this->option('id');
        
        if ($flashcardId) {
            $this->deleteFlashcardById((int)$flashcardId);
        } else {
            $this->deleteFlashcardInteractively($userId);
        }
    }

    /**
     * Delete flashcard by ID
     */
    private function deleteFlashcardById(int $flashcardId): void
    {
        try {
            $this->commandBus->handle(new DeleteFlashcard($flashcardId));
            $this->info("✅ Flashcard {$flashcardId} deleted successfully!");
        } catch (\Exception $e) {
            $this->error("Error deleting flashcard: " . $e->getMessage());
        }
    }

    /**
     * Delete flashcard interactively by showing list and asking for selection
     */
    private function deleteFlashcardInteractively(string $userId): void
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
        $choice = (int) $this->ask('Choose ID to delete');

        if (!$this->confirm("Are you sure you want to delete flashcard {$choice}?")) {
            $this->info('Deletion cancelled.');
            return;
        }

        $this->deleteFlashcardById($choice);
    }
} 