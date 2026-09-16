<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Subject;

/**
 * Seed môn học (subjects) + bài học (lessons) từ map đơn giản:
 *
 *   'Tên môn học' => ['Tên bài 1', 'Tên bài 2'],
 *
 * Idempotent theo slug (tự sinh từ tên). Chạy lại chỉ cập nhật / gắn pivot thiếu.
 *
 *   php artisan db:seed --class='Modules\QuestionBank\Database\Seeders\SubjectLessonSeeder'
 */
final class SubjectLessonSeeder extends Seeder
{
    /**
     * Chỉnh map này để seed dữ liệu khác.
     *
     * @var array<string, list<string>>
     */
    private const MAP = [
        'Anesthesiology' => ['Endocarditis', 'Arrhythmia', 'Hepatitis', 'Hypertension'],
        // 'Tim mạch' => ['STEMI', 'Suy tim', 'Rối loạn nhịp'],
        // 'Hô hấp' => ['Viêm phổi', 'COPD'],
    ];

    public function run(): void
    {
        $subjectSort = 1;

        foreach (self::MAP as $subjectName => $lessonNames) {
            $subjectName = trim((string) $subjectName);
            if ($subjectName === '') {
                continue;
            }

            $subject = Subject::query()->updateOrCreate(
                ['slug' => $this->slug($subjectName)],
                [
                    'name' => $subjectName,
                    'status' => TaxonomyStatus::Active,
                    'sort_order' => $subjectSort,
                ],
            );

            $lessonSort = 1;
            foreach ($lessonNames as $lessonName) {
                $lessonName = trim((string) $lessonName);
                if ($lessonName === '') {
                    continue;
                }

                $lesson = Lesson::query()->updateOrCreate(
                    ['slug' => $this->slug($lessonName)],
                    [
                        'name' => $lessonName,
                        'status' => TaxonomyStatus::Active,
                        'sort_order' => $lessonSort,
                    ],
                );

                $subject->lessons()->syncWithoutDetaching([
                    $lesson->id => ['sort_order' => $lessonSort],
                ]);

                $lessonSort++;
            }

            $subjectSort++;
        }
    }

    private function slug(string $name): string
    {
        $slug = Str::limit((string) Str::slug($name), 191, '');

        return $slug !== '' ? $slug : 'item-'.substr(md5($name), 0, 8);
    }
}
