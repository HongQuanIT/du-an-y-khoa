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
        Schema::create('bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bookmarkable_type', 32);
            $table->string('bookmarkable_id', 64);
            $table->timestamps();

            $table->unique(
                ['user_id', 'bookmarkable_type', 'bookmarkable_id'],
                'bookmarks_owner_target_unique',
            );
            $table->index(
                ['user_id', 'bookmarkable_type'],
                'bookmarks_owner_type_index',
            );
        });

        // `marked` was the temporary bookmark storage before this table existed.
        DB::table('question_status')
            ->where('status', 'marked')
            ->orderBy('id')
            ->each(function (object $status): void {
                DB::table('bookmarks')->insertOrIgnore([
                    'user_id' => $status->user_id,
                    'bookmarkable_type' => 'question',
                    'bookmarkable_id' => (string) $status->question_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $latestAttempt = DB::table('question_attempts')
                    ->where('user_id', $status->user_id)
                    ->where('question_id', $status->question_id)
                    ->whereNotNull('is_correct')
                    ->orderByDesc('answered_at')
                    ->orderByDesc('id')
                    ->first(['is_correct']);

                DB::table('question_status')
                    ->where('id', $status->id)
                    ->update([
                        'status' => $latestAttempt === null
                            ? 'unseen'
                            : ((bool) $latestAttempt->is_correct ? 'correct' : 'incorrect'),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
