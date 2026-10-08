<?php

declare(strict_types=1);

namespace Modules\Exam\Actions;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\Exam\Services\ExamQuotaMatcher;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\CoreClinicalTopic;
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
            $matrix = $learner === null
                ? $this->allocator->allocate($catalog->blueprint)
                : $this->allocator->allocateRandom($catalog->blueprint);
            if (! $matrix['ready']) {
                throw ValidationException::withMessages(['blueprint' => $matrix['reason']]);
            }
            $professionIds = $learner ? [(int) $learner->learnerProfile?->profession_id] : $catalog->professions->pluck('id')->all();
            if (! $professionIds || in_array(0, $professionIds, true)) {
                throw ValidationException::withMessages(['blueprint' => 'Cần cấu hình chức danh cho kỳ thi và học viên.']);
            }
            $difficultyWeights = $catalog->blueprint->difficultyWeights();
            $sampleQuestionIds = $learner !== null && $catalog->sample_exam_id !== null
                ? array_fill_keys(DB::table('exam_question')->where('exam_id', $catalog->sample_exam_id)->pluck('question_id')->all(), true)
                : [];
            $previousQuestionIds = $learner !== null
                ? array_fill_keys(DB::table('exam_question')
                    ->join('exams', 'exams.id', '=', 'exam_question.exam_id')
                    ->where('exams.kind', 'personal')
                    ->where('exams.user_id', $learner->id)
                    ->where('exams.exam_catalog_id', $catalog->id)
                    ->pluck('exam_question.question_id')->all(), true)
                : [];
            $topics = $freshTopics = $withoutSampleTopics = $questions = $names = $candidateTopicIds = [];
            foreach ($matrix['sections'] as $section) {
                $unrestricted = $section['unrestricted_topics'] ?? false;
                $groups = $unrestricted
                    ? [['id' => 'section:'.$section['id'], 'name' => $section['name'], 'question_count' => $section['question_count']]]
                    : $section['topics'];
                $sectionTopics = $unrestricted
                    ? CoreClinicalTopic::query()->whereIn('id', array_column($section['topics'], 'id'))->with('lessons:id', 'tags:id')->get()->keyBy('id')
                    : collect();
                foreach ($groups as $topic) {
                    if ($topic['question_count'] <= 0) {
                        continue;
                    }
                    $groupId = $topic['id'];
                    $query = ServePublishedQuestion::scopeAvailable(Question::query())
                        ->whereHas('examCatalogs', fn ($q) => $q->where('exam_catalogs.id', $catalog->id));
                    foreach ($professionIds as $professionId) {
                        $this->filters->applyProfession($query, $professionId);
                    }
                    if ($unrestricted) {
                        $this->filters->whereMatchesBlueprintSection($query, $section['id'], array_column($section['topics'], 'id'));
                        $priorityIds = [];
                    } else {
                        $this->filters->whereMatchesCoreClinicalTopic($query, $topic['id']);
                        $priorityQuery = clone $query;
                        $this->filters->whereMatchesCoreClinicalTopicPriorityLessons($priorityQuery, $topic['id']);
                        $priorityIds = array_fill_keys($priorityQuery->pluck('id')->all(), true);
                    }
                    $pool = $query->with($unrestricted ? ['options', 'lessons', 'tags'] : ['options', 'lessons'])->get();
                    ServePublishedQuestion::overlayMany($pool);
                    $fresh = $previous = $sample = [];
                    foreach ($pool->shuffle()->sortByDesc(fn (Question $question): bool => isset($priorityIds[$question->id])) as $question) {
                        $id = (string) $question->id;
                        $questions[$id] = $question;
                        $candidateTopicIds[$groupId][$id] = $unrestricted
                            ? $this->matchingTopicId($question, $section['topics'], $sectionTopics)
                            : $topic['id'];
                        $group = match ($question->difficulty->value) {
                            'very_easy', 'easy' => 'easy',
                            'medium' => 'medium',
                            default => 'hard',
                        };
                        if (isset($sampleQuestionIds[$id])) {
                            $sample[$id] = $group;
                        } elseif (isset($previousQuestionIds[$id])) {
                            $previous[$id] = $group;
                        } else {
                            $fresh[$id] = $group;
                        }
                    }
                    $count = $topic['question_count'];
                    $freshTopics[$groupId] = ['count' => $count, 'candidates' => $fresh];
                    $withoutSampleTopics[$groupId] = ['count' => $count, 'candidates' => $fresh + $previous];
                    $topics[$groupId] = ['count' => $count, 'candidates' => $fresh + $previous + $sample];
                    $names[$groupId] = $topic['name'];
                }
            }
            $result = $this->matcher->match($freshTopics, $difficultyWeights);
            if (! $result['complete']) {
                $result = $this->matcher->match($withoutSampleTopics, $difficultyWeights);
            }
            if (! $result['complete']) {
                $result = $this->matcher->match($topics, $difficultyWeights);
            }
            if (! $result['complete']) {
                $details = [];
                foreach ($topics as $id => $topic) {
                    if (count($details) >= 3) {
                        break;
                    }
                    $counts = array_count_values($topic['candidates']);
                    $details[] = sprintf('%s cần %d câu; có %d dễ, %d trung bình, %d khó.', $names[$id], $topic['count'], $counts['easy'] ?? 0, $counts['medium'] ?? 0, $counts['hard'] ?? 0);
                }
                $quotas = $result['quotas'];
                $ratio = implode('/', $difficultyWeights);
                throw ValidationException::withMessages(['blueprint' => sprintf('Ngân hàng câu hỏi chưa đủ để tạo đề đúng tỉ trọng %s (cần %d dễ, %d trung bình, %d khó), kể cả khi dùng lại câu cũ. Ví dụ: ', $ratio, $quotas['easy'], $quotas['medium'], $quotas['hard']).implode(' ', $details)]);
            }
            $reusedCount = count(array_intersect_key($result['selected'], $sampleQuestionIds + $previousQuestionIds));
            $sampleOverlapCount = count(array_intersect_key($result['selected'], $sampleQuestionIds));
            $premiumNumber = $learner !== null
                ? Exam::query()
                    ->where('user_id', $learner->id)
                    ->where('exam_catalog_id', $catalog->id)
                    ->where('kind', 'personal')
                    ->count() + 1
                : null;
            $exam = Exam::query()->create([
                'kind' => $learner ? 'personal' : 'sample',
                'user_id' => $learner?->id,
                'exam_catalog_id' => $catalog->id,
                'blueprint_id' => $catalog->blueprint_id,
                'title' => $catalog->name.($learner ? ' — Q'.$premiumNumber : ' — Bài thi mẫu'),
                'description' => $catalog->description,
                'duration_minutes' => $matrix['suggested_duration_minutes'],
                'status' => $learner ? ExamStatus::Published : ExamStatus::Draft,
                'is_published' => $learner !== null,
                'matrix_snapshot' => array_merge($matrix, [
                    'difficulty_quotas' => $result['quotas'],
                    'profession_ids' => $professionIds,
                ], $learner !== null ? [
                    'premium_sequence' => $premiumNumber,
                    'reused_question_count' => $reusedCount,
                    'sample_overlap_count' => $sampleOverlapCount,
                ] : []),
            ]);
            $pivot = $paper = $actualTopics = [];
            foreach ($topics as $groupId => $topic) {
                foreach ($result['selected'] as $id => $selectedTopic) {
                    if ($selectedTopic !== $groupId) {
                        continue;
                    }
                    $question = $questions[$id];
                    $topicId = $candidateTopicIds[$groupId][$id];
                    $position = count($paper);
                    $pivot[$id] = ['order' => $position + 1, 'core_clinical_topic_id' => $topicId];
                    $paper[] = ['question_id' => $id, 'position' => $position, 'question_version' => (int) ($question->published_version ?: $question->version), 'payload' => $this->snapshots->payload($question, 'exam-'.$exam->id)];
                    $actualTopics[$topicId]['question_count'] = ($actualTopics[$topicId]['question_count'] ?? 0) + 1;
                    $difficulty = $question->difficulty->value;
                    $actualTopics[$topicId]['difficulty_counts'][$difficulty] = ($actualTopics[$topicId]['difficulty_counts'][$difficulty] ?? 0) + 1;
                }
            }
            $sortOrder = 0;
            foreach ($actualTopics as $topicId => $counts) {
                $exam->examTopics()->create(['core_clinical_topic_id' => $topicId, 'question_count' => $counts['question_count'], 'difficulty_counts' => $counts['difficulty_counts'], 'sort_order' => ++$sortOrder]);
            }
            $exam->questions()->sync($pivot);
            $exam->update(['paper_snapshot' => $paper]);

            return $exam;
        });
    }

    /**
     * @param  list<array{id: int}>  $topics
     * @param  Collection<int, CoreClinicalTopic>  $sectionTopics
     */
    private function matchingTopicId(Question $question, array $topics, Collection $sectionTopics): int
    {
        $lessonIds = $question->lessons->modelKeys();
        $tagIds = $question->tags->modelKeys();
        foreach ($topics as $topic) {
            $mapped = $sectionTopics->get($topic['id']);
            if ($mapped && (array_intersect($lessonIds, $mapped->lessons->modelKeys())
                || array_intersect($tagIds, $mapped->tags->modelKeys()))) {
                return (int) $topic['id'];
            }
        }

        throw ValidationException::withMessages(['blueprint' => 'Câu hỏi không còn liên kết với chủ đề trong phần.']);
    }
}
