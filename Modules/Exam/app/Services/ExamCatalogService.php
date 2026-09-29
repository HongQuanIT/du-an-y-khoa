<?php

declare(strict_types=1);

namespace Modules\Exam\Services;

use App\Models\User;
use Illuminate\Support\Collection;
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
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, array<string, mixed>>
     */
    public function blueprintCards(?User $user = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
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
            ->with(['blueprint' => fn ($query) => $query->withCount('sections')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(9);

        $catalogs->getCollection()->transform(function (ExamCatalog $catalog): array {
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

            return [
                'id' => $catalog->id,
                'name' => $catalog->name,
                'code' => $catalog->code,
                'description' => $catalog->description,
                'sections_count' => (int) ($blueprint->sections_count ?? 0),
                'ready' => $matrix['ready'],
                'reason' => $matrix['reason'],
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

    /**
     * @return Collection<int, \Modules\Exam\Models\Exam>
     */
    public function recentExams(User $user): Collection
    {
        return \Modules\Exam\Models\Exam::query()
            ->withCount('questions')
            ->with('blueprint:id,name,code')
            ->where('user_id', $user->getKey())
            ->latest()
            ->limit(6)
            ->get();
    }
}
