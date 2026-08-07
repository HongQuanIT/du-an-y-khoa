<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('current_question_index')->default(0)->after('question_set');
            $table->boolean('chat_muted')->default(false)->after('current_question_index');
            $table->boolean('show_answer')->default(false)->after('chat_muted');
        });

        Schema::table('live_session_messages', function (Blueprint $table): void {
            $table->boolean('is_pinned')->default(false)->after('is_hidden');
        });

        Schema::create('live_session_hands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('raised_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['live_session_id', 'user_id']);
            $table->index(['live_session_id', 'acknowledged_at']);
        });

        Schema::table('live_recordings', function (Blueprint $table): void {
            $table->string('playback_url', 2048)->nullable()->after('egress_id');
            $table->string('hls_manifest', 2048)->nullable()->after('playback_url');
        });
    }

    public function down(): void
    {
        Schema::table('live_recordings', function (Blueprint $table): void {
            $table->dropColumn(['playback_url', 'hls_manifest']);
        });

        Schema::dropIfExists('live_session_hands');

        Schema::table('live_session_messages', function (Blueprint $table): void {
            $table->dropColumn('is_pinned');
        });

        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->dropColumn(['current_question_index', 'chat_muted', 'show_answer']);
        });
    }
};
