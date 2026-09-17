<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Subject;

/**
 * Seed môn học (subjects) + bài học (lessons) từ
 * `database/seeders/data/courses_data.php` (Course → Subject, Subject → Lesson).
 *
 * Idempotent theo slug (tự sinh từ tên). Chạy lại chỉ cập nhật / gắn pivot thiếu.
 *
 *   php artisan db:seed --class='Modules\QuestionBank\Database\Seeders\SubjectLessonSeeder'
 */
final class SubjectLessonSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, list<string>> $map */
        $map = require __DIR__.'/data/courses_data.php';

        $subjectSort = 1;

        foreach ($map as $subjectName => $lessonNames) {
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
