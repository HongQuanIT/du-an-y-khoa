<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_status', function (Blueprint $table): void {
            $table->unsignedInteger('incorrect_count')->default(0)->after('attempts_count');
            $table->unsignedInteger('correct_streak')->default(0)->after('incorrect_count');
            $table->unsignedTinyInteger('mastery_score')->default(0)->after('correct_streak');
            $table->unsignedSmallInteger('review_interval_days')->default(0)->after('mastery_score');
            $table->decimal('ease_factor', 4, 2)->default(2.50)->after('review_interval_days');
            $table->boolean('last_used_hint')->default(false)->after('ease_factor');
            $table->timestamp('next_review_at')->nullable()->after('last_correct_at');
            $table->index(['user_id', 'next_review_at'], 'question_status_user_next_review_index');
        });
    }

    public function down(): void
    {
        Schema::table('question_status', function (Blueprint $table): void {
            $table->dropIndex('question_status_user_next_review_index');
            $table->dropColumn([
                'incorrect_count',
                'correct_streak',
                'mastery_score',
                'review_interval_days',
                'ease_factor',
                'last_used_hint',
                'next_review_at',
            ]);
        });
    }
};
