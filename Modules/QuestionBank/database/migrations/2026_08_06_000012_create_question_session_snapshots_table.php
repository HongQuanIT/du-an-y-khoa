<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_session_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('session_id')->constrained('question_sessions')->cascadeOnDelete();
            // Deliberately no FK: the snapshot must survive a hard-deleted question.
            $table->uuid('question_id');
            $table->unsignedInteger('position');
            $table->unsignedInteger('question_version')->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['session_id', 'question_id'], 'session_snapshots_session_question_unique');
            $table->unique(['session_id', 'position'], 'session_snapshots_session_position_unique');
        });

        Schema::table('question_attempts', function (Blueprint $table): void {
            // Attempts are historical session data and must survive question deletion.
            $table->dropForeign(['question_id']);
        });
    }

    public function down(): void
    {
        Schema::table('question_attempts', function (Blueprint $table): void {
            $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
        });

        Schema::dropIfExists('question_session_snapshots');
    }
};
