<?php

declare(strict_types=1);

namespace Modules\Exam\Services;

use App\Models\User;
use App\Support\TargetExams;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Services\SessionQuestionSelector;

final class ExamCatalogService
{
    public function __construct(private readonly SessionQuestionSelector $selector) {}

    public function cards(User $user): \Illuminate\Pagination\LengthAwarePaginator
    {
        $exams = \Modules\Exam\Models\Exam::withCount('questions')
            ->where('status', \Modules\Exam\Enums\ExamStatus::Published)
            ->paginate(6);

        // Get completed sessions for this user for these exams
        $sessions = QuestionSession::query()
            ->where('user_id', $user->getKey())
            ->where('mode', SessionMode::Exam)
            ->where('source', SessionSource::Exam)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->get()
            ->keyBy('exam_id');

        $exams->getCollection()->transform(function ($exam) use ($sessions) {
            return [
                'id' => $exam->id,
                'title' => $exam->title,
                'icon_url' => $exam->icon ? \Illuminate\Support\Facades\Storage::disk('public')->url($exam->icon) : null,
                'description' => $exam->description,
                'question_count' => $exam->questions_count,
                'duration_minutes' => $exam->duration_minutes,
                'session' => $sessions->get($exam->id), // Will be null if not started/completed
            ];
        });

        return $exams;
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
            ->limit(6)
            ->get();
    }
}
