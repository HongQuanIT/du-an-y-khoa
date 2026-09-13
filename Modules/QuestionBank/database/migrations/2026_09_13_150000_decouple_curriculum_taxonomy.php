<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hệ cơ quan và môn học độc lập (không còn subject_organ_system).
 * Bài học gắn trực tiếp nhiều môn học và nhiều hệ cơ quan (lesson_organ_system).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lesson_organ_system')) {
            Schema::create('lesson_organ_system', function (Blueprint $table): void {
                $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
                $table->foreignId('organ_system_id')->constrained('organ_systems')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->primary(['lesson_id', 'organ_system_id'], 'lesson_organ_system_primary');
                $table->index('organ_system_id', 'lesson_organ_system_os_idx');
            });
        }

        if (Schema::hasTable('subject_organ_system') && Schema::hasTable('lesson_subject')) {
            $now = now();

            $rows = DB::table('lesson_subject')
                ->join('subject_organ_system', 'subject_organ_system.subject_id', '=', 'lesson_subject.subject_id')
                ->select(
                    'lesson_subject.lesson_id',
                    'subject_organ_system.organ_system_id',
                    DB::raw('MIN(subject_organ_system.sort_order) as sort_order'),
                )
                ->groupBy('lesson_subject.lesson_id', 'subject_organ_system.organ_system_id')
                ->get();

            foreach ($rows as $row) {
                $exists = DB::table('lesson_organ_system')
                    ->where('lesson_id', $row->lesson_id)
                    ->where('organ_system_id', $row->organ_system_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('lesson_organ_system')->insert([
                    'lesson_id' => $row->lesson_id,
                    'organ_system_id' => $row->organ_system_id,
                    'sort_order' => (int) $row->sort_order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::dropIfExists('subject_organ_system');
    }

    public function down(): void
    {
        if (! Schema::hasTable('subject_organ_system')) {
            Schema::create('subject_organ_system', function (Blueprint $table): void {
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('organ_system_id')->constrained('organ_systems')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->primary(['subject_id', 'organ_system_id'], 'subject_organ_system_primary');
                $table->index('organ_system_id', 'subject_organ_system_os_idx');
            });
        }

        if (Schema::hasTable('lesson_organ_system') && Schema::hasTable('lesson_subject')) {
            $now = now();

            $rows = DB::table('lesson_organ_system')
                ->join('lesson_subject', 'lesson_subject.lesson_id', '=', 'lesson_organ_system.lesson_id')
                ->select(
                    'lesson_subject.subject_id',
                    'lesson_organ_system.organ_system_id',
                    DB::raw('MIN(lesson_organ_system.sort_order) as sort_order'),
                )
                ->groupBy('lesson_subject.subject_id', 'lesson_organ_system.organ_system_id')
                ->get();

            foreach ($rows as $row) {
                $exists = DB::table('subject_organ_system')
                    ->where('subject_id', $row->subject_id)
                    ->where('organ_system_id', $row->organ_system_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('subject_organ_system')->insert([
                    'subject_id' => $row->subject_id,
                    'organ_system_id' => $row->organ_system_id,
                    'sort_order' => (int) $row->sort_order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::dropIfExists('lesson_organ_system');
    }
};
