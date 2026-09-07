<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_plans', function (Blueprint $table): void {
            $table->decimal('hours_per_day', 4, 2)->default(1)->after('exam_target_date');
            $table->unsignedTinyInteger('questions_per_hour')->default(20)->after('hours_per_day');
            $table->unsignedInteger('total_question_pool')->default(0)->after('questions_per_hour');
            $table->unsignedInteger('selected_question_count')->default(0)->after('total_question_pool');
            $table->decimal('coverage_percent', 5, 2)->default(0)->after('selected_question_count');
        });

        Schema::create('study_plan_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('study_plan_id')->constrained('study_plans')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('question_count')->default(0);
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            $table->unique(['study_plan_id', 'date']);
            $table->index(['study_plan_id', 'status']);
        });

        Schema::create('study_plan_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('study_plan_day_id')->constrained('study_plan_days')->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->string('source_status', 32);
            $table->decimal('high_yield_score', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['study_plan_day_id', 'question_id']);
            $table->index(['study_plan_day_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_plan_questions');
        Schema::dropIfExists('study_plan_days');

        Schema::table('study_plans', function (Blueprint $table): void {
            $table->dropColumn([
                'hours_per_day',
                'questions_per_hour',
                'total_question_pool',
                'selected_question_count',
                'coverage_percent',
            ]);
        });
    }
};
