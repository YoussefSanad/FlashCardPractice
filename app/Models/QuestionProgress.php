<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionProgress extends Model
{
    use HasFactory;

    public const STATUS_NOT_ANSWERED = 'not_answered';
    public const STATUS_CORRECT = 'correct';
    public const STATUS_INCORRECT = 'incorrect';

    protected $fillable = [
        'flashcard_id',
        'user_id',
        'status',
        'last_attempted_at',
    ];

    protected $casts = [
        'last_attempted_at' => 'datetime',
    ];

    /**
     * Get the flashcard this progress belongs to
     */
    public function flashcard(): BelongsTo
    {
        return $this->belongsTo(Flashcard::class);
    }

    /**
     * Check if this question has been answered correctly
     */
    public function isCorrect(): bool
    {
        return $this->status === self::STATUS_CORRECT;
    }

    /**
     * Check if this question has been attempted
     */
    public function isAttempted(): bool
    {
        return $this->status !== self::STATUS_NOT_ANSWERED;
    }
}
