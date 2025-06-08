<?php

namespace App\Console\Commands;

use App\Commands\CreateFlashcard;
use App\Commands\ResetProgress;
use App\Commands\SubmitAnswer;
use App\Queries\GetAllFlashcards;
use App\Queries\GetPracticeableQuestions;
use App\Queries\GetQuestionsWithStatus;
use App\Queries\GetStats;
use Illuminate\Console\Command;
use League\Tactician\CommandBus;
use InvalidArgumentException;
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
            $choice = $this->ask('Please select an option (1-6)');

            try {
                match ($choice) {
                    '1' => $this->createFlashcard(),
                    '2' => $this->listAllFlashcards(),
                    '3' => $this->practiceFlashcards(),
                    '4' => $this->showStats(),
                    '5' => $this->resetProgress(),
                    '6' => $this->exitApplication(),
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
        $this->info('📚 Main Menu:');
        $this->line('1. Create a flashcard');
        $this->line('2. List all flashcards');
        $this->line('3. Practice');
        $this->line('4. Stats');
        $this->line('5. Reset');
        $this->line('6. Exit');
        $this->newLine();
    }

    private function createFlashcard(): void
    {
        $this->info('📝 Create a new flashcard');
        $this->newLine();

        $question = $this->ask('Enter the question');
        if (empty(trim($question))) {
            $this->error('Question cannot be empty.');
            return;
        }

        $answer = $this->ask('Enter the answer');
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

    private function listAllFlashcards(): void
    {
        $this->info('📋 All Flashcards');
        $this->newLine();

        $flashcards = $this->commandBus->handle(new GetAllFlashcards($this->userId));

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

    private function practiceFlashcards(): void
    {
        while (true) {
            // Step 1: Show Progress
            $questions = $this->commandBus->handle(new GetQuestionsWithStatus($this->userId));
            $this->showPracticeProgress($questions);
            if (empty($questions)) {
                return;
            }

            // Step 2: Question Selection
            $practicableQuestions = $this->commandBus->handle(new GetPracticeableQuestions($this->userId));

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
            $this->practiceQuestion($selectedFlashcard);
        }
    }

    private function showPracticeProgress(array $questions): void
    {
        $this->info('📊 Practice Progress');
        $this->newLine();

        $stats = $this->commandBus->handle(new GetStats($this->userId));

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

    private function practiceQuestion($flashcard): void
    {
        $this->info('❓ Question: ' . $flashcard->question);
        $this->newLine();

        $userAnswer = $this->ask('Your answer');

        if (empty(trim($userAnswer))) {
            $this->error('Answer cannot be empty.');
            return;
        }

        try {
            $result = $this->commandBus->handle(new SubmitAnswer($flashcard->id, $userAnswer, $this->userId));

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

    private function showStats(): void
    {
        $this->info('📈 Practice Statistics');
        $this->newLine();

        $stats = $this->commandBus->handle(new GetStats($this->userId));

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

    private function resetProgress(): void
    {
        $this->warn('⚠️  This will reset all practice progress but keep your flashcards.');

        if (!$this->confirm('Are you sure you want to reset all progress?')) {
            $this->info('Reset cancelled.');
            return;
        }

        $result = $this->commandBus->handle(new ResetProgress($this->userId));

        $this->info("✅ Progress reset successfully!");
    }

    private function exitApplication(): void
    {
        $this->info('👋 Thank you for using Flashcard Practice! Goodbye!');
        exit(0);
    }

    private function truncateText(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }
}
