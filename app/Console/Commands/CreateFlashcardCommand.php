<?php

namespace App\Console\Commands;

use App\Commands\CreateFlashcard;
use App\Console\Commands\Traits\FlashcardCommandHelpers;
use Illuminate\Console\Command;
use InvalidArgumentException;
use League\Tactician\CommandBus;

class CreateFlashcardCommand extends Command
{
    use FlashcardCommandHelpers;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flashcard:create {--question= : The flashcard question} {--answer= : The flashcard answer}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new flashcard';

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
        $this->info('📝 Create a new flashcard');
        $this->newLine();

        $question = $this->option('question') ?: $this->ask('Enter the question');
        if (empty(trim($question))) {
            $this->error('Question cannot be empty.');
            return;
        }

        $answer = $this->option('answer') ?: $this->ask('Enter the answer');
        if (empty(trim($answer))) {
            $this->error('Answer cannot be empty.');
            return;
        }

        try {
            $flashcard = $this->commandBus->handle(new CreateFlashcard($question, $answer));
            $this->info("✅ Flashcard created successfully! (ID: {$flashcard->id})");
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
        }
    }
} 