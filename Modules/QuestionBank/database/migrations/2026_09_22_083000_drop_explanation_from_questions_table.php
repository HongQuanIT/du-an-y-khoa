<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop legacy questions.explanation — explanations live only on question_options.
 * Backfill empty correct-option explanations from the question field first.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('questions', 'explanation')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                UPDATE question_options AS o
                INNER JOIN questions AS q ON q.id = o.question_id
                SET o.explanation = q.explanation
                WHERE o.is_correct = 1
                  AND q.explanation IS NOT NULL
                  AND TRIM(q.explanation) <> ''
                  AND (o.explanation IS NULL OR TRIM(o.explanation) = '')
                SQL);
        } else {
            // SQLite / other: row-by-row backfill for tests.
            $rows = DB::table('questions')
                ->whereNotNull('explanation')
                ->where('explanation', '<>', '')
                ->get(['id', 'explanation']);

            foreach ($rows as $row) {
                DB::table('question_options')
                    ->where('question_id', $row->id)
                    ->where('is_correct', true)
                    ->where(function ($query): void {
                        $query->whereNull('explanation')->orWhere('explanation', '');
                    })
                    ->update(['explanation' => $row->explanation]);
            }
        }

        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn('explanation');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('questions', 'explanation')) {
            return;
        }

        Schema::table('questions', function (Blueprint $table): void {
            $table->longText('explanation')->nullable()->after('stem_image_path');
        });
    }
};
