<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('question_blueprint_topics');
    }

    public function down(): void
    {
        if (Schema::hasTable('question_blueprint_topics')) {
            return;
        }

        Schema::create('question_blueprint_topics', function (Blueprint $table): void {
            $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('core_clinical_topic_id')->constrained('core_clinical_topics')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['question_id', 'core_clinical_topic_id']);
            $table->index('core_clinical_topic_id');
        });
    }
};
