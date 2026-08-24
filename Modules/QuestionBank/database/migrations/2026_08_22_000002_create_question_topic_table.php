<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('question_topic')) {
            return;
        }

        Schema::create('question_topic', function (Blueprint $table): void {
            $table->uuid('question_id');
            $table->unsignedBigInteger('topic_id');
            $table->timestamps();

            $table->primary(['question_id', 'topic_id']);
            $table->index(['topic_id', 'question_id']);
            $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
            $table->foreign('topic_id')->references('id')->on('topics')->cascadeOnDelete();
        });

        if (! Schema::hasColumn('questions', 'topic_id')) {
            return;
        }

        DB::table('questions')
            ->whereNotNull('topic_id')
            ->orderBy('id')
            ->get(['id', 'topic_id'])
            ->chunk(500)
            ->each(function ($questions): void {
                $now = now();
                DB::table('question_topic')->insertOrIgnore(
                    $questions->map(fn ($question): array => [
                        'question_id' => $question->id,
                        'topic_id' => $question->topic_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_topic');
    }
};
