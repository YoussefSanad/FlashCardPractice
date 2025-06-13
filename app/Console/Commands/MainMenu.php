<?php

namespace App\Console\Views;

use Illuminate\Console\Command;

class MainMenu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flashcard:main-menu';


    public function handle(): void
    {
        $this->line('📚 Main Menu:');
        $this->line('1. Create a flashcard');
        $this->line('2. List all flashcards');
        $this->line('3. Practice');
        $this->line('4. Stats');
        $this->line('5. Reset');
        $this->line('6. Exit');
        $this->line('7. Delete Flashcard');
        $this->newLine();
    }
}
