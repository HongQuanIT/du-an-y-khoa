<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('session_id')->constrained('question_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
            $table->json('selected_option_ids')->nullable();
            $table->boolean('is_correct')->nullable(); // null = skipped/omitted
            $table->boolean('used_hint')->default(false);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->string('confidence', 16)->nullable();
            $table->boolean('flagged')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index('session_id');
            $table->index(['user_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_attempts');
    }
};
