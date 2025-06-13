<?php

namespace App\Console\Commands;

use App\Commands\SubmitAnswer;
use App\Console\Commands\Traits\FlashcardCommandHelpers;
use App\Queries\GetPracticeableQuestions;
use App\Queries\GetQuestionsWithStatus;
use App\Queries\GetStats;
use Illuminate\Console\Command;
use InvalidArgumentException;
use League\Tactician\CommandBus;

class PracticeFlashcardsCommand extends Command
{
    use FlashcardCommandHelpers;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flashcard:practice {--user-id= : The user ID to practice flashcards for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Practice flashcards interactively';

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

        $this->startPracticeSession($userId);
    }

    /**
     * Start the practice session
     */
    private function startPracticeSession(string $userId): void
    {
        while (true) {
            // Step 1: Show Progress
            $questions = $this->commandBus->handle(new GetQuestionsWithStatus($userId));
            $this->showPracticeProgress($questions, $userId);
            if (empty($questions)) {
                return;
            }

            // Step 2: Question Selection
            $practicableQuestions = $this->commandBus->handle(new GetPracticeableQuestions($userId));

            if ($practicableQuestions->isEmpty()) {
                $this->info('🎉 Congratulations! You have answered all questions correctly!');
                return;
            }

            $this->info('📚 Available questions to practice:');
            $this->newLine();

            $questionOptions = [];
            foreach ($practicableQuestions as $index => $flashcard) {
                $questionNumber = $index + 1;
                $questionOptions[$questionNumber] = $flashcard;
                $this->line("{$questionNumber}. " . $this->truncateText($flashcard->question, 80));
            }

            $this->newLine();
            $this->line('0. Stop practicing');
            $this->newLine();

            $selection = $this->ask('Select a question to practice (0 to stop)');

            if ($selection === '0') {
                $this->info('👋 Practice session ended.');
                return;
            }

            if (!is_numeric($selection) || !isset($questionOptions[(int)$selection])) {
                $this->error(sprintf('Invalid selection. you have to choose a number between 1 and %d', count($questionOptions)));
                continue;
            }

            $selectedFlashcard = $questionOptions[(int)$selection];

            // Step 3: Answer Submission
            $this->practiceQuestion($selectedFlashcard, $userId);
        }
    }

    /**
     * Show practice progress
     */
    private function showPracticeProgress(array $questions, string $userId): void
    {
        $this->info('📊 Practice Progress');
        $this->newLine();

        $stats = $this->commandBus->handle(new GetStats($userId));

        if (empty($questions)) {
            $this->warn('No flashcards found. Create some flashcards first!');
            return;
        }

        $tableData = [];
        foreach ($questions as $questionWithStatus) {
            $tableData[] = [
                'Question' => $this->truncateText($questionWithStatus->question, 60),
                'Status' => $questionWithStatus->status(),
            ];
        }

        $this->table(['Question', 'Status'], $tableData);
        $this->info("Completion: {$stats['correct_percentage']}%");
        $this->newLine();
    }

    /**
     * Practice a specific question
     */
    private function practiceQuestion($flashcard, string $userId): void
    {
        $this->info('❓ Question: ' . $flashcard->question);
        $this->newLine();

        $userAnswer = $this->ask('Your answer');

        if (empty(trim($userAnswer))) {
            $this->error('Answer cannot be empty.');
            return;
        }

        try {
            $result = $this->commandBus->handle(new SubmitAnswer($flashcard->id, $userAnswer, $userId));

            if ($result['is_correct']) {
                $this->info('✅ Correct! Well done!');
            } else {
                $this->error('❌ Incorrect.');
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
        }

        $this->newLine();
    }
} 