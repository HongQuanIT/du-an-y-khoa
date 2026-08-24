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
        Schema::create('question_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 32)->default('save');
            $table->unsignedInteger('restored_from_version')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['question_id', 'version']);
            $table->index(['question_id', 'created_at']);
        });

        $decodeArray = static function (mixed $value): array {
            if (is_array($value)) {
                return array_values($value);
            }

            $decoded = json_decode((string) $value, true);

            return is_array($decoded) ? array_values($decoded) : [];
        };

        DB::table('questions')
            ->orderBy('id')
            ->get()
            ->chunk(200)
            ->each(function ($questions) use ($decodeArray): void {
                $rows = [];

                foreach ($questions as $question) {
                    $topicIds = DB::table('question_topic')
                        ->where('question_id', $question->id)
                        ->pluck('topic_id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();
                    if ($topicIds === [] && Schema::hasColumn('questions', 'topic_id') && $question->topic_id !== null) {
                        $topicIds = [(int) $question->topic_id];
                    }

                    $options = DB::table('question_options')
                        ->where('question_id', $question->id)
                        ->orderBy('order')
                        ->get()
                        ->map(fn ($option): array => [
                            'label' => (string) $option->label,
                            'content' => (string) $option->content,
                            'is_correct' => (bool) $option->is_correct,
                            'explanation' => $option->explanation,
                            'order' => (int) $option->order,
                        ])
                        ->all();

                    $rows[] = [
                        'question_id' => $question->id,
                        'version' => (int) $question->version,
                        'snapshot' => json_encode([
                            'stem' => (string) $question->stem,
                            'stem_image_path' => $question->stem_image_path,
                            'explanation' => $question->explanation,
                            'key_info' => $decodeArray($question->key_info ?? null),
                            'attending_tip' => $question->attending_tip,
                            'difficulty' => (string) $question->difficulty,
                            'status' => (string) $question->status,
                            'topic_ids' => $topicIds,
                            'is_free' => (bool) $question->is_free,
                            'options' => $options,
                        ], JSON_THROW_ON_ERROR),
                        'created_by' => null,
                        'event' => 'baseline',
                        'restored_from_version' => null,
                        'created_at' => $question->updated_at ?? now(),
                    ];
                }

                if ($rows !== []) {
                    DB::table('question_versions')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_versions');
    }
};
