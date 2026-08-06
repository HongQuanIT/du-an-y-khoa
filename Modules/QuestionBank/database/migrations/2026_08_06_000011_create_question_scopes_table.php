<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
            $table->string('scope_type', 16);
            $table->string('scope_key', 64);
            $table->timestamps();

            $table->unique(
                ['question_id', 'scope_type', 'scope_key'],
                'question_scopes_question_type_key_unique',
            );
            $table->index(
                ['scope_type', 'scope_key', 'question_id'],
                'question_scopes_lookup_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_scopes');
    }
};
