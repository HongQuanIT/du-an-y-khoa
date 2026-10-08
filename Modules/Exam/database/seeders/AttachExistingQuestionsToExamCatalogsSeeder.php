<?php

declare(strict_types=1);

namespace Modules\Exam\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\ServePublishedQuestion;
use RuntimeException;

/** Attach existing published questions to every active exam catalog in local test data. */
final class AttachExistingQuestionsToExamCatalogsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Seeder gắn toàn bộ câu hỏi chỉ được chạy trong môi trường local hoặc testing.');
        }

        $catalogs = ExamCatalog::query()
            ->where('status', TaxonomyStatus::Active)
            ->with('professions:id')
            ->get();

        if ($catalogs->isEmpty()) {
            $this->command?->warn('Không có kỳ thi đang hoạt động để gắn câu hỏi.');

            return;
        }

        $catalogIds = $catalogs->pluck('id')->all();
        $professionIds = $catalogs->flatMap(fn (ExamCatalog $catalog) => $catalog->professions->pluck('id'))
            ->unique()->values()->all();
        $linked = 0;

        DB::transaction(function () use ($catalogIds, $professionIds, &$linked): void {
            ServePublishedQuestion::scopeAvailable(Question::query())
                ->select('questions.id')
                ->chunkById(100, function ($questions) use ($catalogIds, $professionIds, &$linked): void {
                    $now = now();
                    $catalogRows = $professionRows = [];

                    foreach ($questions as $question) {
                        foreach ($catalogIds as $catalogId) {
                            $catalogRows[] = [
                                'question_id' => $question->id,
                                'exam_catalog_id' => $catalogId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                        foreach ($professionIds as $professionId) {
                            $professionRows[] = [
                                'question_id' => $question->id,
                                'profession_id' => $professionId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                        $linked++;
                    }

                    DB::table('question_exam_catalogs')->insertOrIgnore($catalogRows);
                    if ($professionRows !== []) {
                        DB::table('question_professions')->insertOrIgnore($professionRows);
                    }
                });
        });

        // The local `abcd` catalog uses placeholder topic names. Map existing lessons
        // to its topics so its 40-question paper can exercise the full generation flow.
        $testCatalog = $catalogs->first(fn (ExamCatalog $catalog): bool => $catalog->code === 'abcd' || $catalog->slug === 'abcd');
        if ($testCatalog?->blueprint_id !== null) {
            $questionIds = ServePublishedQuestion::scopeAvailable(Question::query())->pluck('questions.id');
            $lessonIds = DB::table('question_lesson')
                ->whereIn('question_id', $questionIds)
                ->distinct()
                ->pluck('lesson_id');
            $topicIds = CoreClinicalTopic::query()
                ->where('status', TaxonomyStatus::Active)
                ->whereHas('section', fn ($query) => $query->where('blueprint_id', $testCatalog->blueprint_id))
                ->pluck('id');
            $now = now();
            $rows = [];
            foreach ($topicIds as $topicId) {
                foreach ($lessonIds as $lessonId) {
                    $rows[] = [
                        'core_clinical_topic_id' => $topicId,
                        'lesson_id' => $lessonId,
                        'is_priority' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            if ($rows !== []) {
                DB::table('core_topic_lessons')->insertOrIgnore($rows);
            }
            $this->command?->info("Đã nối bài học của câu hỏi vào {$topicIds->count()} chủ đề của kỳ thi test abcd.");
        }

        $this->command?->info("Đã gắn {$linked} câu hỏi có thể phục vụ vào {$catalogs->count()} kỳ thi local.");
    }
}
