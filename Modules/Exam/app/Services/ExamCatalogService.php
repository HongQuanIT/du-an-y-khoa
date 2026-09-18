<?php

declare(strict_types=1);

namespace Modules\Exam\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Support\BlueprintExamAllocator;

final class ExamCatalogService
{
    public function __construct(private readonly BlueprintExamAllocator $allocator) {}

    /**
     * Kỳ thi = ma trận (blueprint) sẵn sàng để học viên tạo bài thi.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, array<string, mixed>>
     */
    public function blueprintCards(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $blueprints = Blueprint::query()
            ->where('status', TaxonomyStatus::Active)
            ->withCount('sections')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(9);

        $blueprints->getCollection()->transform(function (Blueprint $blueprint): array {
            $matrix = $this->allocator->allocate($blueprint);

            return [
                'id' => $blueprint->id,
                'name' => $blueprint->name,
                'code' => $blueprint->code,
                'description' => $blueprint->description,
                'sections_count' => (int) $blueprint->sections_count,
                'ready' => $matrix['ready'],
                'reason' => $matrix['reason'],
                'question_count' => $matrix['total_questions'],
                'duration_minutes' => $matrix['suggested_duration_minutes'],
                'topic_count' => $matrix['topic_count'],
            ];
        });

        return $blueprints;
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
