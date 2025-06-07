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
    public function practiceStatuses(): HasMany
    {
        return $this->hasMany(PracticeStatus::class);
    }
}
