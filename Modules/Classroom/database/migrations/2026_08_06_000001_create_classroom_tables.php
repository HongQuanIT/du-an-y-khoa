<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->ulid('uuid')->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('visibility', 20)->default('public');
            $table->string('join_code', 16)->nullable()->unique();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('max_members')->nullable();
            $table->unsignedBigInteger('cover_media_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['host_user_id', 'status']);
            $table->index(['visibility', 'status']);
        });

        Schema::create('classroom_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_in_class', 20)->default('member');
            $table->string('status', 20)->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['classroom_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('uuid')->unique();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('title');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->string('livekit_room_name')->nullable();
            $table->unsignedBigInteger('linked_exam_id')->nullable();
            $table->json('question_set')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['classroom_id', 'status']);
            $table->index('scheduled_at');
        });

        Schema::create('live_session_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('type', 20)->default('chat');
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['live_session_id', 'created_at']);
        });

        Schema::create('live_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status', 20)->default('processing');
            $table->string('egress_id')->nullable();
            $table->timestamps();

            $table->index(['live_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_recordings');
        Schema::dropIfExists('live_session_messages');
        Schema::dropIfExists('live_sessions');
        Schema::dropIfExists('classroom_members');
        Schema::dropIfExists('classrooms');
    }
};
