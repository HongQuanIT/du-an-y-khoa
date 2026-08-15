<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 255);
            $table->string('body', 1000);
            $table->string('cta_label', 100)->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->string('variant', 32)->default('info');
            $table->string('placement', 32)->default('both');
            $table->string('audience', 32)->default('all');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_dismissible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_enabled', 'placement', 'sort_order']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
