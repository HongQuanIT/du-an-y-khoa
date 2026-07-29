<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('stem');
            $table->longText('explanation')->nullable();
            $table->string('difficulty', 16)->default('medium');
            $table->string('status', 16)->default('draft');
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->boolean('is_free')->default(false);
            $table->unsignedInteger('version')->default(1); // optimistic locking
            $table->timestamps();
            $table->softDeletes();

            // Hot filter/sort paths (kien-truc §3.1 indexing strategy).
            $table->index(['status', 'difficulty']);
            $table->index(['topic_id', 'status']);
            $table->index('is_free');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
