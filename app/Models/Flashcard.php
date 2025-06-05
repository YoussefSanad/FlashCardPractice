<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Flashcard extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'answer',
    ];

    /**
     * Get all practice attempts for this flashcard
     */
    public function practiceAttempts(): HasMany
    {
        return $this->hasMany(PracticeAttempt::class);
    }

    /**
     * Get the current progress for this flashcard
     */
    public function progress(): HasOne
    {
        return $this->hasOne(QuestionProgress::class);
    }

    /**
     * Get all question progress records for this flashcard
     */
    public function questionProgress(): HasMany
    {
        return $this->hasMany(QuestionProgress::class);
    }

    /**
     * Scope to get flashcards that have been answered correctly
     */
    public function scopeCorrectlyAnswered($query)
    {
        return $query->whereHas('progress', function ($q) {
            $q->where('status', 'correct');
        });
    }

    /**
     * Scope to get flashcards that can be practiced (not correctly answered)
     */
    public function scopePracticeable($query)
    {
        return $query->whereDoesntHave('progress', function ($q) {
            $q->where('status', 'correct');
        });
    }
}
