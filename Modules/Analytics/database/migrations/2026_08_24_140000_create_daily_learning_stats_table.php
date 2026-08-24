<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_learning_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('questions_answered')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->unsignedInteger('study_seconds')->default(0);
            $table->unsignedInteger('completed_sessions')->default(0);
            $table->boolean('daily_goal_reached')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'date', 'daily_goal_reached'], 'daily_stats_user_date_goal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_learning_stats');
    }
};
