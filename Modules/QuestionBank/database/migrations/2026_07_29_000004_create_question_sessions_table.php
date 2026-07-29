<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode', 16)->default('study'); // study/exam
            $table->string('status', 16)->default('active');
            $table->json('filters')->nullable();
            $table->json('question_ids')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('answered_count')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('time_limit_seconds')->nullable();
            $table->json('paused_state')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Dashboard "Continue learning" + history lookups.
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_sessions');
    }
};
