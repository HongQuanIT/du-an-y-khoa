<?php

declare(strict_types=1);

namespace Modules\Exam\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Support\BlueprintExamAllocator;

final class ExamCatalogService
{
    public function __construct(private readonly BlueprintExamAllocator $allocator) {}

    /**
     * Kỳ thi đã gắn ma trận, và thuộc chức danh của học viên khi hồ sơ có chức danh.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function blueprintCards(?User $user = null): LengthAwarePaginator
    {
        $professionId = $user?->learnerProfile?->profession_id;

        $catalogs = ExamCatalog::query()
            ->where('status', TaxonomyStatus::Active)
            ->whereNotNull('blueprint_id')
            ->when($professionId !== null, function ($query) use ($professionId): void {
                $query->whereHas(
                    'professions',
                    fn ($professions) => $professions->where('professions.id', (int) $professionId),
                );
            }, function ($query): void {
                $query->whereRaw('0 = 1');
            })
            ->with(['blueprint' => fn ($query) => $query->withCount('sections'), 'sampleExam'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(9);

        $catalogs->getCollection()->transform(function (ExamCatalog $catalog) use ($professionId): array {
            $blueprint = $catalog->blueprint;
            $matrix = $blueprint !== null
                ? $this->allocator->allocate($blueprint)
                : [
                    'ready' => false,
                    'reason' => 'Kỳ thi chưa gắn ma trận.',
                    'total_questions' => 0,
                    'suggested_duration_minutes' => 0,
                    'topic_count' => 0,
                ];
            $premiumMatrix = $blueprint !== null && $matrix['ready']
                ? $this->allocator->allocateRandom($blueprint)
                : $matrix;

            return [
                'id' => $catalog->id,
                'sample_exam_id' => $catalog->sampleExam?->isPublished() && in_array((int) $professionId, $catalog->sampleExam->matrix_snapshot['profession_ids'] ?? [], true) ? $catalog->sample_exam_id : null,
                'sample_question_count' => count($catalog->sampleExam?->paper_snapshot ?? []),
                'sample_duration_minutes' => $catalog->sampleExam?->duration_minutes,
                'difficulty_quotas' => app(ExamQuotaMatcher::class)->quotas($matrix['total_questions'], $blueprint->difficultyWeights()),
                'sample_difficulty_quotas' => $catalog->sampleExam?->matrix_snapshot['difficulty_quotas'] ?? [],
                'name' => $catalog->name,
                'code' => $catalog->code,
                'description' => $catalog->description,
                'sections_count' => (int) ($blueprint->sections_count ?? 0),
                'ready' => $matrix['ready'],
                'reason' => $matrix['reason'],
                'premium_ready' => $premiumMatrix['ready'],
                'premium_reason' => $premiumMatrix['reason'],
                'section_ranges' => collect($matrix['sections'] ?? [])->map(function (array $section) use ($matrix): array {
                    $total = $matrix['total_questions'];
                    $min = $section['weight_min'] ?? $section['weight_max'];
                    $max = $section['weight_max'] ?? $section['weight_min'];

                    return [
                        'name' => $section['name'],
                        'min' => (int) ceil($total * $min / 100 - 0.0000001),
                        'max' => (int) floor($total * $max / 100 + 0.0000001),
                    ];
                })->all(),
                'question_count' => $matrix['total_questions'],
                'duration_minutes' => $matrix['suggested_duration_minutes'],
                'topic_count' => $matrix['topic_count'],
            ];
        });

        return $catalogs;
    }

    /**
     * @return Collection<int, QuestionSession>
     */
    public function recentSessions(User $user): Collection
    {
        return QuestionSession::query()
            ->where('user_id', $user->getKey())
            ->where('mode', SessionMode::Exam)
            ->where('source', SessionSource::Exam)
            ->whereNotNull('exam_id')
            ->latest('updated_at')
            ->limit(8)
            ->get();
    }

    /** @return LengthAwarePaginator<int, Exam> */
    public function recentExams(User $user): LengthAwarePaginator
    {
        return Exam::query()
            ->select([
                'exams.id',
                'exams.blueprint_id',
                'exams.title',
                'exams.duration_minutes',
                'exams.created_at',
            ])
            ->withCount('questions')
            ->with('blueprint:id,name,code')
            ->where('user_id', $user->getKey())
            ->where('kind', 'personal')
            ->latest('exams.created_at')
            ->paginate(6, ['*'], 'exam_page');
    }
}
