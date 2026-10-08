<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\Profession;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use RuntimeException;

/**
 * Tạo 1.000 câu đã xuất bản; mỗi câu phủ 1–3 bài học và gắn toàn bộ đối tượng, ma trận, kỳ thi.
 * Seeder chỉ dành cho local/testing và có thể chạy lại mà không tạo câu trùng.
 */
final class PublishedUniversalQuestionSeeder extends Seeder
{
    private const TOTAL = 1000;

    private const PREFIX = 'QBANK-REAL-';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Seeder 1.000 câu QBank chỉ được chạy trong môi trường local hoặc testing.');
        }

        $lessonIds = Lesson::query()->orderBy('id')->pluck('id')->all();
        $professionIds = Profession::query()->pluck('id')->all();
        $catalogIds = ExamCatalog::query()->pluck('id')->all();
        $blueprintIds = Blueprint::query()->pluck('id')->all();

        if ($lessonIds === [] || $professionIds === [] || $catalogIds === []) {
            throw new RuntimeException('Cần có bài học, đối tượng và kỳ thi trước khi seed 1.000 câu QBank.');
        }

        Question::withoutSyncingToSearch(function () use ($lessonIds, $professionIds, $catalogIds, $blueprintIds): void {
            for ($start = 1; $start <= self::TOTAL; $start += 50) {
                $end = min(self::TOTAL, $start + 49);

                DB::transaction(function () use ($start, $end, $lessonIds, $professionIds, $catalogIds, $blueprintIds): void {
                    $questionIds = [];
                    foreach (range($start, $end) as $number) {
                        $code = self::PREFIX.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
                        $question = Question::withTrashed()->where('code', $code)->first() ?? new Question;
                        if (! $question->exists) {
                            $question->code = $code;
                        } elseif ($question->trashed()) {
                            $question->restore();
                        }

                        $question->forceFill([
                            'stem' => '<p>Câu '.$number.': Câu này đúng hay sai?</p>',
                            'key_info' => ['Chọn đáp án đúng.'],
                            'attending_tip' => '<p>Đây là câu hỏi dữ liệu QBank phục vụ kiểm thử.</p>',
                            'difficulty' => $this->difficultyFor($number),
                            'status' => QuestionStatus::Published,
                            'is_free' => true,
                            'is_priority' => true,
                            'version' => 1,
                            'published_version' => 1,
                        ])->save();
                        $questionIds[] = (string) $question->id;
                    }

                    $this->replaceOptions($questionIds);
                    $this->replaceLessonPivots($questionIds, $lessonIds, $start);
                    $this->replacePivot('question_professions', 'profession_id', $questionIds, $professionIds);
                    $this->replacePivot('question_exam_catalogs', 'exam_catalog_id', $questionIds, $catalogIds);
                    $this->replacePivot('question_blueprints', 'blueprint_id', $questionIds, $blueprintIds);
                });

                $this->command?->info("Đã seed câu {$start}–{$end}/".self::TOTAL.'.');
            }
        });

        $this->command?->info('Hoàn tất 1.000 câu đã xuất bản; mỗi câu gắn 1–3 bài học và toàn bộ đối tượng, kỳ thi.');
    }

    private function difficultyFor(int $number): Difficulty
    {
        return match (($number - 1) % 20) {
            0, 1, 2, 3 => Difficulty::VeryEasy,
            4, 5, 6, 7 => Difficulty::Easy,
            8, 9, 10, 11, 12, 13 => Difficulty::Medium,
            14, 15, 16 => Difficulty::Hard,
            default => Difficulty::VeryHard,
        };
    }

    /** @param list<string> $questionIds */
    private function replaceOptions(array $questionIds): void
    {
        DB::table('question_options')->whereIn('question_id', $questionIds)->delete();
        $now = now();
        $rows = [];
        foreach ($questionIds as $questionId) {
            foreach ([
                ['A', 'Đúng', true],
                ['B', 'Sai', false],
                ['C', 'Sai', false],
                ['D', 'Sai', false],
            ] as $index => [$label, $content, $correct]) {
                $rows[] = [
                    'question_id' => $questionId,
                    'label' => $label,
                    'content' => $content,
                    'is_correct' => $correct,
                    'explanation' => $correct ? 'Đáp án đúng.' : 'Đáp án sai.',
                    'order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('question_options')->insert($rows);
    }

    /**
     * @param list<string> $questionIds
     * @param list<int> $lessonIds
     */
    private function replaceLessonPivots(array $questionIds, array $lessonIds, int $startNumber): void
    {
        DB::table('question_lesson')->whereIn('question_id', $questionIds)->delete();
        $lessonCount = count($lessonIds);
        $now = now();
        $rows = [];

        foreach ($questionIds as $offset => $questionId) {
            $number = $startNumber + $offset;
            $links = 1 + (($number - 1) % 3);
            $firstLesson = (($number - 1) * 2) % $lessonCount;
            for ($index = 0; $index < $links; $index++) {
                $rows[] = [
                    'question_id' => $questionId,
                    'lesson_id' => $lessonIds[($firstLesson + $index) % $lessonCount],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('question_lesson')->insert($rows);
    }

    /**
     * @param list<string> $questionIds
     * @param list<int> $relatedIds
     */
    private function replacePivot(string $table, string $relatedColumn, array $questionIds, array $relatedIds): void
    {
        DB::table($table)->whereIn('question_id', $questionIds)->delete();
        if ($relatedIds === []) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($questionIds as $questionId) {
            foreach ($relatedIds as $relatedId) {
                $rows[] = [
                    'question_id' => $questionId,
                    $relatedColumn => $relatedId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
