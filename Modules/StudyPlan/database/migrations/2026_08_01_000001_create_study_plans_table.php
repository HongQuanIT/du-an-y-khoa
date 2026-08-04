<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('exam_key', 32)->nullable(); // target exam picked in the wizard
            $table->date('exam_target_date');
            $table->unsignedInteger('daily_goal_questions')->default(20);
            $table->unsignedInteger('daily_goal_minutes')->default(45);
            $table->json('topic_scope')->nullable(); // topic ids the plan draws questions from
            $table->json('study_days')->nullable(); // ISO weekdays (1=Mon) the learner studies
            $table->string('strategy', 16)->default('fixed'); // fixed/adaptive
            $table->string('status', 16)->default('active'); // active/paused/completed
            $table->json('progress_cache')->nullable();
            $table->timestamp('replanned_at')->nullable();
            $table->timestamps();

            // Overview page + dashboard widget both look up the active plan.
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_plans');
    }
};
