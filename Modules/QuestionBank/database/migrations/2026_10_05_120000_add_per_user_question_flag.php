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
        Schema::table('question_status', function (Blueprint $table): void {
            $table->boolean('flagged')->default(false)->after('status');
            $table->index(['user_id', 'flagged']);
        });

        DB::table('question_attempts')
            ->where('flagged', true)
            ->select(['user_id', 'question_id'])
            ->distinct()
            ->orderBy('user_id')
            ->orderBy('question_id')
            ->chunk(500, function ($attempts): void {
                foreach ($attempts as $attempt) {
                    DB::table('question_status')->updateOrInsert(
                        ['user_id' => $attempt->user_id, 'question_id' => $attempt->question_id],
                        ['flagged' => true, 'updated_at' => now(), 'created_at' => now()],
                    );
                }
            });

        DB::table('question_sessions')
            ->whereNotNull('annotations')
            ->orderBy('id')
            ->chunk(250, function ($sessions): void {
                foreach ($sessions as $session) {
                    $annotations = json_decode((string) $session->annotations, true);
                    if (! is_array($annotations)) {
                        continue;
                    }

                    $changed = false;
                    foreach ($annotations as $questionId => $annotation) {
                        if (! is_array($annotation)) {
                            continue;
                        }

                        if ($annotation['flagged'] ?? false) {
                            DB::table('question_status')->updateOrInsert(
                                ['user_id' => $session->user_id, 'question_id' => $questionId],
                                ['flagged' => true, 'updated_at' => now(), 'created_at' => now()],
                            );
                        }

                        if (array_key_exists('flagged', $annotation)) {
                            unset($annotations[$questionId]['flagged']);
                            $changed = true;
                        }
                    }

                    if ($changed) {
                        DB::table('question_sessions')
                            ->where('id', $session->id)
                            ->update(['annotations' => json_encode($annotations, JSON_THROW_ON_ERROR)]);
                    }
                }
            });

        DB::table('question_attempts')->where('flagged', true)->update(['flagged' => false]);
    }

    public function down(): void
    {
        Schema::table('question_status', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'flagged']);
            $table->dropColumn('flagged');
        });
    }
};
