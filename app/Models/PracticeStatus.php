<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeStatus extends Model
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
}
