<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kỳ thi is its own catalog. A row may point at one blueprint (ma trận) or none.
 * Existing blueprints become kỳ thi that keep that matrix, and question links move with them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_catalogs')) {
            Schema::create('exam_catalogs', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('code')->nullable()->unique();
                $table->text('description')->nullable();
                $table->foreignId('blueprint_id')->nullable()->constrained('blueprints')->nullOnDelete();
                $table->string('status')->default('active');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('exam_catalog_professions')) {
            Schema::create('exam_catalog_professions', function (Blueprint $table): void {
                $table->foreignId('exam_catalog_id')->constrained('exam_catalogs')->cascadeOnDelete();
                $table->foreignId('profession_id')->constrained('professions')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['exam_catalog_id', 'profession_id']);
                $table->index('profession_id');
            });
        }

        if (! Schema::hasTable('question_exam_catalogs')) {
            Schema::create('question_exam_catalogs', function (Blueprint $table): void {
                $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
                $table->foreignId('exam_catalog_id')->constrained('exam_catalogs')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['question_id', 'exam_catalog_id']);
                $table->index('exam_catalog_id');
            });
        }

        if (Schema::hasTable('exams') && ! Schema::hasColumn('exams', 'exam_catalog_id')) {
            Schema::table('exams', function (Blueprint $table): void {
                $table->foreignId('exam_catalog_id')
                    ->nullable()
                    ->after('blueprint_id')
                    ->constrained('exam_catalogs')
                    ->nullOnDelete();
            });
        }

        $this->copyBlueprintsIntoCatalogs();
    }

    public function down(): void
    {
        if (Schema::hasTable('exams') && Schema::hasColumn('exams', 'exam_catalog_id')) {
            Schema::table('exams', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('exam_catalog_id');
            });
        }

        Schema::dropIfExists('question_exam_catalogs');
        Schema::dropIfExists('exam_catalog_professions');
        Schema::dropIfExists('exam_catalogs');
    }

    private function copyBlueprintsIntoCatalogs(): void
    {
        if (! Schema::hasTable('blueprints') || ! Schema::hasTable('exam_catalogs')) {
            return;
        }

        $blueprints = DB::table('blueprints')->orderBy('id')->get();
        $now = now();

        foreach ($blueprints as $blueprint) {
            $already = DB::table('exam_catalogs')->where('blueprint_id', $blueprint->id)->exists();
            if ($already) {
                continue;
            }

            $slug = $this->uniqueSlug((string) ($blueprint->slug ?: Str::slug((string) $blueprint->name)));
            $code = filled($blueprint->code) ? (string) $blueprint->code : null;
            if ($code !== null && DB::table('exam_catalogs')->where('code', $code)->exists()) {
                $code = null;
            }

            $catalogId = DB::table('exam_catalogs')->insertGetId([
                'name' => $blueprint->name,
                'slug' => $slug,
                'code' => $code,
                'description' => $blueprint->description,
                'blueprint_id' => $blueprint->id,
                'status' => $blueprint->status,
                'sort_order' => (int) $blueprint->sort_order,
                'created_at' => $blueprint->created_at ?? $now,
                'updated_at' => $blueprint->updated_at ?? $now,
            ]);

            if (Schema::hasTable('blueprint_professions')) {
                $professionIds = DB::table('blueprint_professions')
                    ->where('blueprint_id', $blueprint->id)
                    ->pluck('profession_id');
                foreach ($professionIds as $professionId) {
                    DB::table('exam_catalog_professions')->insert([
                        'exam_catalog_id' => $catalogId,
                        'profession_id' => $professionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (Schema::hasTable('question_blueprints')) {
                $questionIds = DB::table('question_blueprints')
                    ->where('blueprint_id', $blueprint->id)
                    ->pluck('question_id');
                foreach ($questionIds as $questionId) {
                    DB::table('question_exam_catalogs')->insertOrIgnore([
                        'question_id' => $questionId,
                        'exam_catalog_id' => $catalogId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    private function uniqueSlug(string $slug): string
    {
        $base = $slug !== '' ? $slug : 'ky-thi';
        $candidate = $base;
        $suffix = 1;

        while (DB::table('exam_catalogs')->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
};
