<?php

namespace App\Providers;

use App\Commands\CreateFlashcard;
use App\Commands\CreateFlashcardHandler;
use App\Commands\ResetProgress;
use App\Commands\ResetProgressHandler;
use App\Commands\SubmitAnswer;
use App\Commands\SubmitAnswerHandler;
use App\Middleware\IdempotencyMiddleware;
use App\Middleware\LoggingMiddleware;
use App\Middleware\TransactionMiddleware;
use App\Queries\GetAllFlashcards;
use App\Queries\GetAllFlashcardsHandler;
use App\Queries\GetPracticeableQuestions;
use App\Queries\GetPracticeableQuestionsHandler;
use App\Queries\GetQuestionsWithStatus;
use App\Queries\GetQuestionsWithStatusHandler;
use App\Queries\GetStats;
use App\Queries\GetStatsHandler;
use Illuminate\Support\ServiceProvider;
use League\Tactician\CommandBus;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\CommandNameExtractor\ClassNameExtractor;
use League\Tactician\Handler\Locator\InMemoryLocator;
use League\Tactician\Handler\MethodNameInflector\HandleInflector;

class CqrsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CommandBus::class, function ($app) {
            $locator = new InMemoryLocator();

            // Register command handlers
            $locator->addHandler($app->make(CreateFlashcardHandler::class), CreateFlashcard::class);
            $locator->addHandler($app->make(SubmitAnswerHandler::class), SubmitAnswer::class);
            $locator->addHandler($app->make(ResetProgressHandler::class), ResetProgress::class);

            // Register query handlers
            $locator->addHandler($app->make(GetAllFlashcardsHandler::class), GetAllFlashcards::class);
            $locator->addHandler($app->make(GetQuestionsWithStatusHandler::class), GetQuestionsWithStatus::class);
            $locator->addHandler($app->make(GetStatsHandler::class), GetStats::class);
            $locator->addHandler($app->make(GetPracticeableQuestionsHandler::class), GetPracticeableQuestions::class);

            $handlerMiddleware = new CommandHandlerMiddleware(
                new ClassNameExtractor(),
                $locator,
                new HandleInflector()
            );

            return new CommandBus([
                new LoggingMiddleware(),
                new IdempotencyMiddleware(),
                new TransactionMiddleware(),
                $handlerMiddleware
            ]);
        });

        // Register handlers as singletons
        $this->app->singleton(CreateFlashcardHandler::class);
        $this->app->singleton(SubmitAnswerHandler::class);
        $this->app->singleton(ResetProgressHandler::class);
        $this->app->singleton(GetAllFlashcardsHandler::class);
        $this->app->singleton(GetQuestionsWithStatusHandler::class);
        $this->app->singleton(GetStatsHandler::class);
        $this->app->singleton(GetPracticeableQuestionsHandler::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
