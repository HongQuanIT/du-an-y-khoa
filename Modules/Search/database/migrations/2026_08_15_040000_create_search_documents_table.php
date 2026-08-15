<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_type');
            $table->uuid('source_id')->nullable();
            $table->string('scope', 32)->index();
            $table->string('type', 32)->index();
            $table->string('title');
            $table->string('summary', 500)->nullable();
            $table->longText('body');
            $table->string('url')->nullable();
            $table->boolean('is_free')->default(true)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_type', 'source_id']);
            $table->unique(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_documents');
    }
};
