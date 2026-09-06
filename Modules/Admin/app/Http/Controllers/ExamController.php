<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamTopic;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionFilterBuilder;

final class ExamController extends Controller
{
    public function index(): View
    {
        $exams = Exam::query()
            ->withCount('questions')
            ->latest()
            ->paginate(20);

        return view('admin::exams.index', compact('exams'));
    }

    public function create(): View
    {
        $exam = new Exam;
        $exam->duration_minutes = 90;
        $exam->status = ExamStatus::Draft;
        $exam->is_published = false;
        $exam->setAttribute('questions_count', 0);

        $availableQuestions = $this->availableQuestions();
        $difficultyLevels = $this->difficultyLevelsForView();

        return view('admin::exams.form', compact('exam', 'availableQuestions', 'difficultyLevels'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedExam($request);
        $status = ExamStatus::from($validated['status']);
        $examTopicsInput = $this->normalizedExamTopicsInput($validated['exam_topics'] ?? []);

        if ($status === ExamStatus::Published && $examTopicsInput === [] && count($validated['questions'] ?? []) === 0) {
            return back()
                ->withErrors(['status' => 'Phải cấu hình phân bổ ma trận hoặc thêm ít nhất 1 câu hỏi trước khi xuất bản.'])
                ->withInput();
        }

        $iconPath = null;
        if ($request->hasFile('icon')) {
            $iconPath = $request->file('icon')->store('exams', 'public');
        }

        $exam = Exam::create([
            'blueprint_id' => $validated['blueprint_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'icon' => $iconPath,
            'status' => $status,
            'is_published' => $status === ExamStatus::Published,
        ]);

        $this->syncExamTopics($exam, $examTopicsInput);

        $syncResult = $this->resolveQuestionSync($exam, $validated['questions'] ?? [], $examTopicsInput);
        if ($syncResult['errors'] !== []) {
            $exam->delete();

            return back()->withErrors(['exam_topics' => implode(' ', $syncResult['errors'])])->withInput();
        }

        if ($status === ExamStatus::Published && count($syncResult['sync']) === 0) {
            $exam->delete();

            return back()
                ->withErrors(['status' => 'Phải có ít nhất 1 câu hỏi sau khi generate từ ma trận đề thi.'])
                ->withInput();
        }

        $exam->questions()->sync($syncResult['sync']);

        return redirect()->route('admin.exams.edit', $exam)->with('status', 'Kỳ thi đã được tạo.');
    }

    public function edit(Exam $exam): View
    {
        $exam->loadCount('questions');
        $exam->load([
            'questions' => fn ($q) => $q->with(['medicalTaxonomyNodes'])->orderBy('exam_question.order'),
            'examTopics.coreClinicalTopic.section',
            'blueprint',
        ]);

        $availableQuestions = $this->availableQuestions();
        $difficultyLevels = $this->difficultyLevelsForView();

        return view('admin::exams.form', compact('exam', 'availableQuestions', 'difficultyLevels'));
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $validated = $this->validatedExam($request);
        $status = ExamStatus::from($validated['status']);
        $examTopicsInput = $this->normalizedExamTopicsInput($validated['exam_topics'] ?? []);

        if ($status === ExamStatus::Published && $examTopicsInput === [] && count($validated['questions'] ?? []) === 0) {
            return back()
                ->withErrors(['status' => 'Phải cấu hình phân bổ ma trận hoặc thêm ít nhất 1 câu hỏi trước khi xuất bản.'])
                ->withInput();
        }

        $data = [
            'blueprint_id' => $validated['blueprint_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'status' => $status,
            'is_published' => $status === ExamStatus::Published,
        ];

        if ($request->hasFile('icon')) {
            $data['icon'] = $request->file('icon')->store('exams', 'public');
        }

        $exam->update($data);

        $this->syncExamTopics($exam, $examTopicsInput);

        $syncResult = $this->resolveQuestionSync($exam, $validated['questions'] ?? [], $examTopicsInput);
        if ($syncResult['errors'] !== []) {
            return back()->withErrors(['exam_topics' => implode(' ', $syncResult['errors'])])->withInput();
        }

        if ($status === ExamStatus::Published && count($syncResult['sync']) === 0) {
            return back()
                ->withErrors(['status' => 'Phải có ít nhất 1 câu hỏi sau khi generate từ ma trận đề thi.'])
                ->withInput();
        }

        $exam->questions()->sync($syncResult['sync']);

        return redirect()->route('admin.exams.index')->with('status', 'Đã cập nhật kỳ thi.');
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        $exam->delete();

        return redirect()->route('admin.exams.index')->with('status', 'Đã xóa kỳ thi.');
    }

    public function topicEligibility(Request $request): JsonResponse
    {
        $topicIds = collect($request->input('core_clinical_topic_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $counts = [];
        foreach ($topicIds as $topicId) {
            $byDifficulty = $this->eligibleQuestionCountsByDifficulty($topicId);
            $counts[$topicId] = [
                'total' => array_sum($byDifficulty),
                'by_difficulty' => $byDifficulty,
            ];
        }

        return response()->json(['data' => $counts]);
    }

    public function searchQuestions(Request $request): JsonResponse
    {
        $term = trim($request->input('q', ''));

        $query = Question::query()
            ->with(['medicalTaxonomyNodes'])
            ->latest();

        if ($term !== '') {
            $termLower = mb_strtolower($term);

            $difficulties = [];
            foreach (Difficulty::cases() as $case) {
                if (str_contains(mb_strtolower($case->label()), $termLower)) {
                    $difficulties[] = $case->value;
                }
            }

            $query->where(function ($q) use ($term, $difficulties) {
                $q->where('stem', 'LIKE', "%{$term}%")
                    ->orWhereHas('medicalTaxonomyNodes', function ($q2) use ($term) {
                        $q2->where('name', 'LIKE', "%{$term}%");
                    });

                if (! empty($difficulties)) {
                    $q->orWhereIn('difficulty', $difficulties);
                }
            });
        }

        $questions = $query->limit(50)->get()->map(fn ($question) => [
            'id' => (string) $question->id,
            'text' => strip_tags($question->stem),
            'topic' => $question->medicalTaxonomyNodes->pluck('name')->join(', ') ?: 'Tổng hợp',
            'topics' => $question->medicalTaxonomyNodes->pluck('name')->values()->all(),
            'difficulty' => $question->difficulty?->label(),
        ])->values()->all();

        return response()->json($questions);
    }

    private function availableQuestions()
    {
        return Question::query()
            ->with(['medicalTaxonomyNodes'])
            ->latest()
            ->limit(50)
            ->get();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function difficultyLevelsForView(): array
    {
        return array_map(
            fn (Difficulty $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            Difficulty::cases(),
        );
    }

    /** @return array<string, mixed> */
    private function validatedExam(Request $request): array
    {
        $difficultyRules = [];
        foreach (Difficulty::cases() as $case) {
            $difficultyRules['exam_topics.*.difficulty_counts.'.$case->value] = 'nullable|integer|min:0';
        }

        return $request->validate(array_merge([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:1',
            'icon' => 'nullable|file|image|max:2048',
            'status' => ['required', 'string', Rule::in(ExamStatus::values())],
            'blueprint_id' => 'nullable|integer|exists:blueprints,id',
            'section_ids' => 'nullable|array',
            'section_ids.*' => 'integer|exists:blueprint_sections,id',
            'questions' => 'nullable|array',
            'questions.*' => 'exists:questions,id',
            'exam_topics' => 'nullable|array',
            'exam_topics.*.core_clinical_topic_id' => 'required_with:exam_topics|integer|exists:core_clinical_topics,id',
            'exam_topics.*.difficulty_counts' => 'nullable|array',
            'exam_topics.*.sort_order' => 'nullable|integer|min:0',
        ], $difficultyRules));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{core_clinical_topic_id: int, difficulty_counts: array<string, int>, question_count: int, sort_order: int}>
     */
    private function normalizedExamTopicsInput(array $rows): array
    {
        $normalized = [];

        foreach (array_values($rows) as $index => $row) {
            $topicId = (int) ($row['core_clinical_topic_id'] ?? 0);
            if ($topicId <= 0) {
                continue;
            }

            $counts = ExamTopic::normalizeDifficultyCounts(
                is_array($row['difficulty_counts'] ?? null) ? $row['difficulty_counts'] : null
            );
            $total = ExamTopic::sumDifficultyCounts($counts);
            if ($total <= 0) {
                continue;
            }

            $normalized[] = [
                'core_clinical_topic_id' => $topicId,
                'difficulty_counts' => $counts,
                'question_count' => $total,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{core_clinical_topic_id: int, difficulty_counts: array<string, int>, question_count: int, sort_order: int}>  $rows
     */
    private function syncExamTopics(Exam $exam, array $rows): void
    {
        $exam->examTopics()->delete();

        foreach (array_values($rows) as $index => $row) {
            ExamTopic::query()->create([
                'exam_id' => $exam->id,
                'core_clinical_topic_id' => $row['core_clinical_topic_id'],
                'difficulty_counts' => $row['difficulty_counts'],
                'question_count' => $row['question_count'],
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ]);
        }
    }

    /**
     * @param  array<int, string>  $manualQuestionIds
     * @param  array<int, array<string, mixed>>  $examTopicsInput
     * @return array{sync: array<string, array{order: int, core_clinical_topic_id?: int|null}>, errors: array<int, string>}
     */
    private function resolveQuestionSync(Exam $exam, array $manualQuestionIds, array $examTopicsInput): array
    {
        if ($examTopicsInput !== []) {
            return $this->generateQuestionsFromExamTopics($exam);
        }

        return [
            'sync' => $this->questionSyncData($manualQuestionIds),
            'errors' => [],
        ];
    }

    /**
     * @return array{sync: array<string, array{order: int, core_clinical_topic_id?: int|null}>, errors: array<int, string>}
     */
    private function generateQuestionsFromExamTopics(Exam $exam): array
    {
        $exam->load('examTopics.coreClinicalTopic');
        $usedQuestionIds = [];
        $syncData = [];
        $order = 1;
        $errors = [];
        $filterBuilder = app(QuestionFilterBuilder::class);

        foreach ($exam->examTopics as $examTopic) {
            $topicName = $examTopic->coreClinicalTopic?->name ?? ('#'.$examTopic->core_clinical_topic_id);
            $counts = $examTopic->difficultyCountsOrEmpty();

            foreach (Difficulty::cases() as $difficulty) {
                $needed = $counts[$difficulty->value] ?? 0;
                if ($needed <= 0) {
                    continue;
                }

                $questionIds = Question::query()
                    ->where('status', QuestionStatus::Private)
                    ->where('exam_flag', true)
                    ->where('difficulty', $difficulty->value)
                    ->tap(fn ($query) => $filterBuilder->whereMatchesCoreClinicalTopic(
                        $query,
                        (int) $examTopic->core_clinical_topic_id,
                    ))
                    ->whereNotIn('id', $usedQuestionIds)
                    ->orderByDesc('created_at')
                    ->limit($needed)
                    ->pluck('id');

                if ($questionIds->count() < $needed) {
                    $available = $this->eligibleQuestionCountForDifficulty(
                        (int) $examTopic->core_clinical_topic_id,
                        $difficulty,
                    );
                    $errors[] = sprintf(
                        '%s / %s cần %d câu nhưng chỉ có %d eligible (thiếu %d).',
                        $topicName,
                        $difficulty->label(),
                        $needed,
                        $available,
                        $needed - $questionIds->count(),
                    );
                }

                foreach ($questionIds as $questionId) {
                    $syncData[(string) $questionId] = [
                        'order' => $order++,
                        'core_clinical_topic_id' => $examTopic->core_clinical_topic_id,
                    ];
                    $usedQuestionIds[] = (string) $questionId;
                }
            }
        }

        return ['sync' => $syncData, 'errors' => $errors];
    }

    /**
     * @return array<string, int>
     */
    private function eligibleQuestionCountsByDifficulty(int $coreClinicalTopicId): array
    {
        $counts = ExamTopic::emptyDifficultyCounts();
        $filterBuilder = app(QuestionFilterBuilder::class);

        $rows = Question::query()
            ->selectRaw('difficulty, COUNT(*) as aggregate')
            ->where('status', QuestionStatus::Private)
            ->where('exam_flag', true)
            ->tap(fn ($query) => $filterBuilder->whereMatchesCoreClinicalTopic($query, $coreClinicalTopicId))
            ->groupBy('difficulty')
            ->pluck('aggregate', 'difficulty');

        foreach ($rows as $difficulty => $aggregate) {
            $key = (string) $difficulty;
            if (array_key_exists($key, $counts)) {
                $counts[$key] = (int) $aggregate;
            }
        }

        return $counts;
    }

    private function eligibleQuestionCountForDifficulty(int $coreClinicalTopicId, Difficulty $difficulty): int
    {
        return Question::query()
            ->where('status', QuestionStatus::Private)
            ->where('exam_flag', true)
            ->where('difficulty', $difficulty->value)
            ->tap(fn ($query) => app(QuestionFilterBuilder::class)
                ->whereMatchesCoreClinicalTopic($query, $coreClinicalTopicId))
            ->count();
    }

    /**
     * @param  array<int, string>  $questionIds
     * @return array<string, array{order: int}>
     */
    private function questionSyncData(array $questionIds): array
    {
        $syncData = [];

        foreach ($questionIds as $index => $questionId) {
            $syncData[$questionId] = ['order' => $index + 1];
        }

        return $syncData;
    }
}
