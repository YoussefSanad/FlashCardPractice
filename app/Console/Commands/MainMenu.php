<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MainMenu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flashcard:main-menu';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display the flashcard application main menu';

    /**
     * Menu options configuration
     *
     * @var array
     */
    private array $menuOptions = [
        '1' => 'Create a flashcard',
        '2' => 'List all flashcards', 
        '3' => 'Practice',
        '4' => 'Stats',
        '5' => 'Reset',
        '6' => 'Exit',
        '7' => 'Delete Flashcard',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->displayMenu();
    }

    /**
     * Display the main menu options
     */
    private function displayMenu(): void
    {
        $this->line('📚 Main Menu:');
        
        foreach ($this->menuOptions as $key => $option) {
            $this->line("{$key}. {$option}");
        }
        
        $this->newLine();
    }

    /**
     * Get available menu options
     *
     * @return array
     */
    public function getMenuOptions(): array
    {
        return $this->menuOptions;
    }
}
