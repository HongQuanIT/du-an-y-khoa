<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('session_id');
            $table->string('area', 180);
            $table->string('portal', 20);
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('heartbeat_count')->default(1);
            $table->string('ip', 45)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('device_name', 100)->nullable();
            $table->string('operating_system', 100)->nullable();
            $table->string('browser', 100)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'session_id', 'area'], 'activity_user_session_area_unique');
            $table->index(['user_id', 'last_seen_at'], 'activity_user_last_seen_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_sessions');
    }
};
