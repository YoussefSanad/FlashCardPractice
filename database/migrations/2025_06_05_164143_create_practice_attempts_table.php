<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('practice_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flashcard_id');
            $table->uuid('user_id');
            $table->text('user_answer');
            $table->boolean('is_correct');
            $table->timestamps();

            // Index for efficient querying by flashcard & user
            $table->index(['user_id', 'flashcard_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practice_attempts');
    }
};
