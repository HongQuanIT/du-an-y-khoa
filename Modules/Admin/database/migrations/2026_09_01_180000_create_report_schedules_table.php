<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('category_slug', 40);
            $table->string('report_slug', 40);
            $table->string('range_key', 10)->default('30d');
            $table->string('frequency', 16); // daily|weekly|monthly
            $table->unsignedTinyInteger('weekday')->nullable(); // 1=Mon … 7=Sun (weekly)
            $table->unsignedTinyInteger('day_of_month')->nullable(); // 1–28 (monthly)
            $table->time('send_time')->default('08:00:00');
            $table->json('recipients');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamps();

            $table->index(['category_slug', 'report_slug']);
            $table->index(['is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
