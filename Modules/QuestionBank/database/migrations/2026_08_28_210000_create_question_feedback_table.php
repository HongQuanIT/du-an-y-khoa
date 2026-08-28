<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('question_id');
            $table->uuid('question_session_id');
            $table->unsignedBigInteger('question_option_id')->nullable();
            $table->string('target', 24);
            $table->string('category', 48);
            $table->text('message')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
            $table->foreign('question_session_id')->references('id')->on('question_sessions')->cascadeOnDelete();
            $table->foreign('question_option_id')->references('id')->on('question_options')->nullOnDelete();
            $table->index(['question_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_feedback');
    }
};
