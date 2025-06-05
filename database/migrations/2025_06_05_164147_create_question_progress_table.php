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
        Schema::create('question_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flashcard_id')->constrained()->onDelete('cascade');
            $table->uuid('user_id');
            $table->enum('status', ['not_answered', 'correct', 'incorrect'])->default('not_answered');
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            // Ensure one progress record per flashcard & user
            $table->unique(['user_id', 'flashcard_id']);

            // Index for efficient status filtering
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_progress');
    }
};
