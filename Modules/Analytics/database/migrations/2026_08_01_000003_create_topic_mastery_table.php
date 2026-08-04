<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_mastery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('correct')->default(0);
            $table->decimal('correct_rate', 5, 2)->default(0);
            $table->unsignedTinyInteger('mastery_level')->default(0); // 0-5
            $table->timestamp('last_activity_at')->nullable();
            $table->json('trend')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'topic_id']);
            // Weak-topics list reads the lowest rates for a user.
            $table->index(['user_id', 'correct_rate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_mastery');
    }
};
