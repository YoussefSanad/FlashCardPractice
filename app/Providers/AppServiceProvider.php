<?php

namespace App\Providers;

use App\Repositories\FlashcardRepository;
use App\Repositories\EloquentFlashcardRepository;
use App\Repositories\QuestionProgressRepository;
use App\Repositories\EloquentQuestionProgressRepository;
use App\Repositories\PracticeAttemptRepository;
use App\Repositories\EloquentPracticeAttemptRepository;
use App\Time\Clock;
use App\Time\SystemClock;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FlashcardRepository::class, EloquentFlashcardRepository::class);
        $this->app->bind(QuestionProgressRepository::class, EloquentQuestionProgressRepository::class);
        $this->app->bind(PracticeAttemptRepository::class, EloquentPracticeAttemptRepository::class);
        $this->app->bind(Clock::class, SystemClock::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
