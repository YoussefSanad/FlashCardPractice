<?php

namespace App\Console\Commands;


use Illuminate\Console\Command;

use League\Tactician\CommandBus;
use Ramsey\Uuid\Uuid;

class FlashcardInteractiveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flashcard:interactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactive flashcard practice application';

    private CommandBus $commandBus;
    private string $userId;

    public function __construct(CommandBus $commandBus)
    {
        parent::__construct();
        $this->commandBus = $commandBus;
        $this->userId = Uuid::uuid7()->toString();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🎯 Welcome to Flashcard Practice!');
        $this->info("👤 Session ID: {$this->userId}");
        $this->newLine();

        while (true) {
            $this->showMainMenu();
            $choice = $this->ask('Please select an option (1-7)');

            try {
                match ($choice) {
                    '1' => $this->createFlashcard(),
                    '2' => $this->listAllFlashcards(),
                    '3' => $this->practiceFlashcards(),
                    '4' => $this->showStats(),
                    '5' => $this->resetProgress(),
                    '6' => $this->exitApplication(),
                    '7' => $this->deleteFlashcard(),
                    default => $this->error('Invalid option. Please select 1-6.'),
                };
            } catch (\Exception $e) {
                $this->error('Error: ' . $e->getMessage());
            }

            $this->newLine();
        }
    }

    private function showMainMenu(): void
    {
        $this->call('flashcard:main-menu');
    }

    private function createFlashcard(): void
    {
        $this->call('flashcard:create');
    }

    private function listAllFlashcards(): void
    {
        $this->call('flashcard:list', ['--user-id' => $this->userId]);
    }

    private function practiceFlashcards(): void
    {
        $this->call('flashcard:practice', ['--user-id' => $this->userId]);
    }

    private function showStats(): void
    {
        $this->call('flashcard:stats', ['--user-id' => $this->userId]);
    }

    private function resetProgress(): void
    {
        $this->call('flashcard:reset', ['--user-id' => $this->userId]);
    }

    private function exitApplication(): void
    {
        $this->info('👋 Thank you for using Flashcard Practice! Goodbye!');
        exit(0);
    }

    private function deleteFlashcard(): void
    {
        $this->call('flashcard:delete', ['--user-id' => $this->userId]);
    }


}
