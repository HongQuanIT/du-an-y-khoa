<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_threads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            // Context the thread is anchored to (question/article/…), spoiler-safe.
            $table->string('context_type', 20)->nullable();
            $table->string('context_id')->nullable();
            $table->string('context_source', 20)->nullable();
            $table->string('session_id')->nullable();
            $table->string('preset', 40)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'context_type', 'context_id']);
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('thread_id')->constrained('ai_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12); // user | assistant
            $table->string('status', 12)->default('done'); // pending | streaming | done | failed | stopped
            $table->string('preset', 40)->nullable();
            $table->longText('content')->nullable();
            $table->json('citations')->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->string('feedback_vote', 8)->nullable(); // up | down
            $table->timestamps();

            $table->index(['thread_id', 'created_at']);
        });

        // Reconciliation ledger for the daily free quota (one row per user/day).
        Schema::create('ai_usage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_threads');
    }
};
