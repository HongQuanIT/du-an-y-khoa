<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('live_sessions', 'host_grace_until')) {
            Schema::table('live_sessions', function (Blueprint $table): void {
                $table->timestamp('host_grace_until')->nullable()->after('ended_at');
                $table->unsignedInteger('expected_duration_seconds')->nullable()->after('host_grace_until');
                $table->index(['status', 'host_grace_until']);
            });
        }

        if (! Schema::hasTable('live_session_attendance_segments')) {
            Schema::create('live_session_attendance_segments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('joined_at');
                $table->timestamp('left_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['live_session_id', 'user_id', 'joined_at'], 'live_attendance_session_user_joined_idx');
            });
        } else {
            Schema::table('live_session_attendance_segments', function (Blueprint $table): void {
                $table->index(['live_session_id', 'user_id', 'joined_at'], 'live_attendance_session_user_joined_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('live_session_attendance_segments');
        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->dropIndex(['status', 'host_grace_until']);
            $table->dropColumn(['host_grace_until', 'expected_duration_seconds']);
        });
    }
};
