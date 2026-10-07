<?php

declare(strict_types=1);

namespace Modules\Exam\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Exam\Services\ExamQuotaMatcher;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Support\BlueprintExamAllocator;

/**
 * Tạo ngân hàng câu hỏi đã xuất bản đủ cho các kỳ thi đang dùng ma trận.
 * Seeder idempotent: chạy lại sẽ cập nhật các câu có mã EXAM-BP-*, không nhân bản.
 */
final class BlueprintExamQuestionSeeder extends Seeder
{
    private const QUESTIONS_PER_QUOTA = 3;

    private const ABCD_CATALOG_CODE = 'abcd';

    private const ABCD_QUESTION_COUNT = 600;

    public function run(BlueprintExamAllocator $allocator, ExamQuotaMatcher $matcher, ?string $catalogCode = null): void
    {
        $blueprintIds = ExamCatalog::query()
            ->where('status', TaxonomyStatus::Active)
            ->whereNotNull('blueprint_id')
            ->when($catalogCode !== null, fn ($query) => $query->where(fn ($catalogs) => $catalogs
                ->where('code', $catalogCode)->orWhere('slug', $catalogCode)))
            ->pluck('blueprint_id');
        $blueprints = Blueprint::query()
            ->whereIn('id', $blueprintIds)
            ->whereHas('sections.coreClinicalTopics')
            ->get();

        if ($blueprints->isEmpty()) {
            $this->command?->warn('Không có ma trận nào được gắn với kỳ thi đang hoạt động.');

            return;
        }

        Question::withoutSyncingToSearch(function () use ($blueprints, $allocator, $matcher, $catalogCode): void {
            foreach ($blueprints as $blueprint) {
                $matrix = $allocator->allocate($blueprint);
                if (! $matrix['ready']) {
                    $this->command?->warn("Bỏ qua ma trận {$blueprint->name}: {$matrix['reason']}");

                    continue;
                }

                $catalogs = ExamCatalog::query()
                    ->where('blueprint_id', $blueprint->id)
                    ->where('status', TaxonomyStatus::Active)
                    ->when($catalogCode !== null, fn ($query) => $query->where(fn ($catalogs) => $catalogs
                        ->where('code', $catalogCode)->orWhere('slug', $catalogCode)))
                    ->with('professions:id')
                    ->get();
                $catalogIds = $catalogs->pluck('id')->map(fn ($id): int => (int) $id)->all();
                $professionIds = $catalogs
                    ->flatMap(fn (ExamCatalog $catalog) => $catalog->professions)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                $totalQuestions = (int) $matrix['total_questions'];
                $targetCount = $catalogs->contains(fn (ExamCatalog $catalog): bool => $catalog->code === self::ABCD_CATALOG_CODE || $catalog->slug === self::ABCD_CATALOG_CODE)
                    ? self::ABCD_QUESTION_COUNT
                    : $totalQuestions * self::QUESTIONS_PER_QUOTA;
                $baseCopies = intdiv($targetCount, $totalQuestions);
                $extraCopies = $targetCount % $totalQuestions;
                $difficulties = $this->difficultyPlan($totalQuestions, $matcher);
                $position = 0;
                $seededDifficulties = ['easy' => 0, 'medium' => 0, 'hard' => 0];

                DB::transaction(function () use ($blueprint, $matrix, $catalogIds, $professionIds, $difficulties, $baseCopies, $extraCopies, &$position, &$seededDifficulties): void {
                    foreach ($matrix['sections'] as $section) {
                        foreach ($section['topics'] as $topicQuota) {
                            for ($topicIndex = 1; $topicIndex <= $topicQuota['question_count']; $topicIndex++) {
                                $difficulty = $difficulties[$position++];
                                $copies = $baseCopies + ($position <= $extraCopies ? 1 : 0);
                                for ($copy = 0; $copy < $copies; $copy++) {
                                    $this->seedQuestion(
                                        $blueprint,
                                        (int) $topicQuota['id'],
                                        (string) $topicQuota['name'],
                                        $topicIndex + $copy * $topicQuota['question_count'],
                                        $difficulty,
                                        $catalogIds,
                                        $professionIds,
                                    );
                                    $group = match ($difficulty) {
                                        Difficulty::VeryEasy, Difficulty::Easy => 'easy',
                                        Difficulty::Medium => 'medium',
                                        default => 'hard',
                                    };
                                    $seededDifficulties[$group]++;
                                }
                            }
                        }
                    }
                });

                $this->command?->info(sprintf(
                    'Đã seed %d câu cho ma trận «%s»: %d dễ, %d trung bình, %d khó.',
                    $targetCount,
                    $blueprint->name,
                    $seededDifficulties['easy'],
                    $seededDifficulties['medium'],
                    $seededDifficulties['hard'],
                ));
            }
        });
    }

    /** @return list<Difficulty> */
    private function difficultyPlan(int $total, ExamQuotaMatcher $matcher): array
    {
        $quotas = $matcher->quotas($total);
        $easyFirst = intdiv($quotas['easy'], 2);
        $hardFirst = intdiv($quotas['hard'], 2);

        return [
            ...array_fill(0, $easyFirst, Difficulty::VeryEasy),
            ...array_fill(0, $quotas['easy'] - $easyFirst, Difficulty::Easy),
            ...array_fill(0, $quotas['medium'], Difficulty::Medium),
            ...array_fill(0, $hardFirst, Difficulty::Hard),
            ...array_fill(0, $quotas['hard'] - $hardFirst, Difficulty::VeryHard),
        ];
    }

    /**
     * @param  list<int>  $catalogIds
     * @param  list<int>  $professionIds
     */
    private function seedQuestion(
        Blueprint $blueprint,
        int $topicId,
        string $topicName,
        int $topicIndex,
        Difficulty $difficulty,
        array $catalogIds,
        array $professionIds,
    ): void {
        $topic = CoreClinicalTopic::query()->findOrFail($topicId);
        $lesson = $topic->lessons()->orderByPivot('is_priority', 'desc')->first();
        if (! $lesson instanceof Lesson) {
            $lesson = Lesson::query()->firstOrCreate(
                ['slug' => 'exam-cct-'.$topicId],
                [
                    'name' => $topicName,
                    'description' => 'Bài học phục vụ ma trận đề thi '.$blueprint->name.'.',
                    'status' => TaxonomyStatus::Active,
                    'sort_order' => $topic->sort_order,
                ],
            );
            $topic->lessons()->syncWithoutDetaching([$lesson->id => ['is_priority' => true]]);
        }

        $code = sprintf('EXAM-BP%03d-T%03d-Q%03d', $blueprint->id, $topicId, $topicIndex);
        $question = Question::withTrashed()->where('code', $code)->first();
        $payload = [
            'stem' => sprintf(
                '<p>Câu %d về <strong>%s</strong>: lựa chọn nhận định phù hợp nhất trong tình huống lâm sàng giả định.</p>',
                $topicIndex,
                e($topicName),
            ),
            'key_info' => ['Xác định dữ kiện chính của chủ đề '.$topicName.'.'],
            'attending_tip' => '<p>Đối chiếu triệu chứng, dấu hiệu và nguyên tắc xử trí phù hợp.</p>',
            'difficulty' => $difficulty,
            'status' => QuestionStatus::Published,
            'is_free' => true,
            'is_priority' => true,
            'version' => 1,
            'published_version' => 1,
        ];

        if ($question instanceof Question) {
            if ($question->trashed()) {
                $question->restore();
            }
            $question->forceFill($payload)->save();
        } else {
            $question = new Question;
            $question->code = $code;
            $question->forceFill($payload)->save();
        }

        $question->lessons()->sync([$lesson->id]);
        $question->blueprints()->syncWithoutDetaching([$blueprint->id]);
        $question->examCatalogs()->syncWithoutDetaching($catalogIds);
        $question->professions()->syncWithoutDetaching($professionIds);
        $this->syncOptions($question, $topicName);
    }

    private function syncOptions(Question $question, string $topicName): void
    {
        QuestionOption::query()->where('question_id', $question->id)->delete();
        $options = [
            'Nhận định phù hợp nhất với dữ kiện của '.$topicName,
            'Nhận định không phù hợp vì thiếu dữ kiện chính',
            'Chỉ theo dõi mà không cần đánh giá thêm',
            'Bỏ qua triệu chứng và dấu hiệu hiện có',
        ];

        foreach ($options as $index => $content) {
            QuestionOption::query()->create([
                'question_id' => $question->id,
                'label' => chr(ord('A') + $index),
                'content' => $content,
                'is_correct' => $index === 0,
                'explanation' => $index === 0 ? 'Đây là đáp án phù hợp nhất với chủ đề '.$topicName.'.' : null,
                'order' => $index + 1,
            ]);
        }
    }
}
