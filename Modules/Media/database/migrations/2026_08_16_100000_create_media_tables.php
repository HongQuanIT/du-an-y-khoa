<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 32);
            $table->string('disk', 32);
            $table->string('path', 512);
            $table->json('variants')->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('alt', 255)->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('credit', 255)->nullable();
            $table->boolean('is_premium')->default(false);
            $table->string('status', 32)->default('processing');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
        });

        Schema::create('media_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('usable_type');
            $table->unsignedBigInteger('usable_id');
            $table->timestamps();

            $table->unique(['media_id', 'usable_type', 'usable_id']);
            $table->index(['usable_type', 'usable_id']);
        });

        Schema::create('media_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('status', 32);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_jobs');
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media');
    }
};
