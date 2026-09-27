<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('question_professions')) {
            Schema::create('question_professions', function (Blueprint $table): void {
                $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
                $table->foreignId('profession_id')->constrained('professions')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['question_id', 'profession_id']);
                $table->index('profession_id');
            });
        }

        if (! Schema::hasTable('question_blueprints')) {
            Schema::create('question_blueprints', function (Blueprint $table): void {
                $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
                $table->foreignId('blueprint_id')->constrained('blueprints')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['question_id', 'blueprint_id']);
                $table->index('blueprint_id');
            });
        }

        if (! Schema::hasTable('blueprint_professions')) {
            Schema::create('blueprint_professions', function (Blueprint $table): void {
                $table->foreignId('blueprint_id')->constrained('blueprints')->cascadeOnDelete();
                $table->foreignId('profession_id')->constrained('professions')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['blueprint_id', 'profession_id']);
                $table->index('profession_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprint_professions');
        Schema::dropIfExists('question_blueprints');
        Schema::dropIfExists('question_professions');
    }
};
