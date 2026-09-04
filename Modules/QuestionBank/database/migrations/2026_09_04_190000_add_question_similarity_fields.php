<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->char('content_fingerprint', 64)->nullable()->after('stats_updated_at');
            $table->timestamp('similarity_checked_at')->nullable()->after('content_fingerprint');
            $table->index('content_fingerprint');
        });

        Schema::create('question_similarity_matches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('question_id_low');
            $table->uuid('question_id_high');
            $table->decimal('score', 5, 2);
            $table->string('severity', 32);
            $table->json('signals')->nullable();
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamps();

            $table->unique(['question_id_low', 'question_id_high'], 'qsm_pair_unique');
            $table->index(['severity', 'score']);
            $table->index('question_id_low');
            $table->index('question_id_high');

            $table->foreign('question_id_low')
                ->references('id')
                ->on('questions')
                ->cascadeOnDelete();
            $table->foreign('question_id_high')
                ->references('id')
                ->on('questions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_similarity_matches');

        Schema::table('questions', function (Blueprint $table): void {
            $table->dropIndex(['content_fingerprint']);
            $table->dropColumn(['content_fingerprint', 'similarity_checked_at']);
        });
    }
};
