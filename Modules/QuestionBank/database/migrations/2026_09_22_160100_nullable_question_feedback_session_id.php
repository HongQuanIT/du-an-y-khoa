<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keep content-ops feedback when learner sessions are force-deleted (progress reset).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_feedback', function (Blueprint $table): void {
            $table->dropForeign(['question_session_id']);
        });

        Schema::table('question_feedback', function (Blueprint $table): void {
            $table->uuid('question_session_id')->nullable()->change();
            $table->foreign('question_session_id')
                ->references('id')
                ->on('question_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('question_feedback', function (Blueprint $table): void {
            $table->dropForeign(['question_session_id']);
        });

        Schema::table('question_feedback', function (Blueprint $table): void {
            $table->uuid('question_session_id')->nullable(false)->change();
            $table->foreign('question_session_id')
                ->references('id')
                ->on('question_sessions')
                ->cascadeOnDelete();
        });
    }
};
