<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_plan_reminder_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('reminder_date');
            $table->unsignedSmallInteger('task_count')->default(0);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['user_id', 'reminder_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_plan_reminder_logs');
    }
};
