<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_plan_id')->constrained('study_plans')->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 16)->default('questions'); // questions/read/flashcards/review
            $table->unsignedInteger('target')->default(0);
            $table->unsignedInteger('done')->default(0);
            $table->string('status', 16)->default('pending'); // pending/done/skipped
            $table->json('ref')->nullable(); // {topic_ids, session_id, mode}
            $table->timestamps();

            // Calendar/detail read by date; "today" panel filters by status.
            $table->index(['study_plan_id', 'date']);
            $table->index(['study_plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_plan_tasks');
    }
};
