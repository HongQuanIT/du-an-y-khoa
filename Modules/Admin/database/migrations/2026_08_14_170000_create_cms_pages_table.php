<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('slug', 128)->unique();
            $table->string('title', 255);
            $table->json('content')->nullable();
            $table->json('seo')->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
