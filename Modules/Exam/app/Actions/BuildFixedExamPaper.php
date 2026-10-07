<?php

declare(strict_types=1);

namespace Modules\Exam\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\Exam\Services\ExamQuotaMatcher;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Services\QuestionSessionSnapshots;
use Modules\QuestionBank\Support\BlueprintExamAllocator;
use Modules\QuestionBank\Support\QuestionFilterBuilder;
use Modules\QuestionBank\Support\ServePublishedQuestion;

final class BuildFixedExamPaper
{
    public function __construct(
        private BlueprintExamAllocator $allocator,
        private QuestionFilterBuilder $filters,
        private ExamQuotaMatcher $matcher,
        private QuestionSessionSnapshots $snapshots,
    ) {}

    public function handle(ExamCatalog $catalog, ?User $learner = null): Exam
    {
        return DB::transaction(function () use ($catalog, $learner): Exam {
            $catalog = ExamCatalog::query()->lockForUpdate()->findOrFail($catalog->id);
            $catalog->load('blueprint', 'professions');
            if ($catalog->status !== TaxonomyStatus::Active || $catalog->blueprint?->status !== TaxonomyStatus::Active) {
                throw ValidationException::withMessages(['blueprint' => 'Kỳ thi và ma trận phải đang hoạt động.']);
            }
            $matrix = $this->allocator->allocate($catalog->blueprint);
            if (! $matrix['ready']) {
                throw ValidationException::withMessages(['blueprint' => $matrix['reason']]);
            }
            $professionIds = $learner ? [(int) $learner->learnerProfile?->profession_id] : $catalog->professions->pluck('id')->all();
            if (! $professionIds || in_array(0, $professionIds, true)) {
                throw ValidationException::withMessages(['blueprint' => 'Cần cấu hình chức danh cho kỳ thi và học viên.']);
            }
            $topics = $questions = $names = [];
            foreach ($matrix['sections'] as $section) {
                foreach ($section['topics'] as $topic) {
                    if ($topic['question_count'] <= 0) {
                        continue;
                    }
                    $query = ServePublishedQuestion::scopeAvailable(Question::query())
                        ->whereHas('examCatalogs', fn ($q) => $q->where('exam_catalogs.id', $catalog->id));
                    foreach ($professionIds as $professionId) {
                        $this->filters->applyProfession($query, $professionId);
                    }
                    $this->filters->whereMatchesCoreClinicalTopic($query, $topic['id']);
                    $priorityQuery = clone $query;
                    $this->filters->whereMatchesCoreClinicalTopicPriorityLessons($priorityQuery, $topic['id']);
                    $priorityIds = array_fill_keys($priorityQuery->pluck('id')->all(), true);
                    $pool = $query->with('options', 'lessons')->get();
                    ServePublishedQuestion::overlayMany($pool);
                    $candidates = [];
                    foreach ($pool->shuffle()->sortByDesc(fn (Question $question): bool => isset($priorityIds[$question->id])) as $question) {
                        $id = (string) $question->id;
                        $questions[$id] = $question;
                        $candidates[$id] = match ($question->difficulty->value) {
                            'very_easy', 'easy' => 'easy',
                            'medium' => 'medium',
                            default => 'hard',
                        };
                    }
                    $topics[$topic['id']] = ['count' => $topic['question_count'], 'candidates' => $candidates];
                    $names[$topic['id']] = $topic['name'];
                }
            }
            $result = $this->matcher->match($topics);
            if (! $result['complete']) {
                $details = [];
                foreach ($topics as $id => $topic) {
                    $counts = array_count_values($topic['candidates']);
                    $details[] = sprintf('%s cần %d câu; có %d dễ, %d trung bình, %d khó.', $names[$id], $topic['count'], $counts['easy'] ?? 0, $counts['medium'] ?? 0, $counts['hard'] ?? 0);
                }
                $quotas = $result['quotas'];
                throw ValidationException::withMessages(['blueprint' => sprintf('Không đủ câu không trùng để đáp ứng ma trận và tỷ lệ 40/30/30 (cần %d dễ, %d trung bình, %d khó). ', $quotas['easy'], $quotas['medium'], $quotas['hard']).implode(' ', $details)]);
            }
            $exam = Exam::query()->create([
                'kind' => $learner ? 'personal' : 'sample',
                'user_id' => $learner?->id,
                'exam_catalog_id' => $catalog->id,
                'blueprint_id' => $catalog->blueprint_id,
                'title' => $catalog->name.($learner ? '' : ' — Bài thi mẫu'),
                'description' => $catalog->description,
                'duration_minutes' => $matrix['suggested_duration_minutes'],
                'status' => $learner ? ExamStatus::Published : ExamStatus::Draft,
                'is_published' => $learner !== null,
                'matrix_snapshot' => array_merge($matrix, ['difficulty_quotas' => $result['quotas'], 'profession_ids' => $professionIds]),
            ]);
            $pivot = $paper = [];
            foreach ($topics as $topicId => $topic) {
                $difficultyCounts = [];
                foreach ($result['selected'] as $id => $selectedTopic) {
                    if ($selectedTopic !== $topicId) {
                        continue;
                    }
                    $question = $questions[$id];
                    $position = count($paper);
                    $pivot[$id] = ['order' => $position + 1, 'core_clinical_topic_id' => $topicId];
                    $paper[] = ['question_id' => $id, 'position' => $position, 'question_version' => (int) ($question->published_version ?: $question->version), 'payload' => $this->snapshots->payload($question, 'exam-'.$exam->id)];
                    $difficultyCounts[$question->difficulty->value] = ($difficultyCounts[$question->difficulty->value] ?? 0) + 1;
                }
                $exam->examTopics()->create(['core_clinical_topic_id' => $topicId, 'question_count' => $topic['count'], 'difficulty_counts' => $difficultyCounts, 'sort_order' => count($pivot)]);
            }
            $exam->questions()->sync($pivot);
            $exam->update(['paper_snapshot' => $paper]);

            return $exam;
        });
    }
}
