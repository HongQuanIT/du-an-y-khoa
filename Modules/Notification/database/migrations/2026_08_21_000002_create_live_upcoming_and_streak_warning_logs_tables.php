<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_upcoming_reminder_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['live_session_id', 'user_id'], 'live_upcoming_session_user_unique');
        });

        Schema::create('streak_warning_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('warning_date');
            $table->unsignedInteger('streak_count')->default(0);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['user_id', 'warning_date'], 'streak_warning_user_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streak_warning_logs');
        Schema::dropIfExists('live_upcoming_reminder_logs');
    }
};
