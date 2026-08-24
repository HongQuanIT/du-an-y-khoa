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
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Question;

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

        return view('admin::exams.form', compact('exam', 'availableQuestions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedExam($request);
        $status = ExamStatus::from($validated['status']);
        $examTopicsInput = $validated['exam_topics'] ?? [];

        if ($status === ExamStatus::Published && count($examTopicsInput) === 0 && count($validated['questions'] ?? []) === 0) {
            return back()
                ->withErrors(['status' => 'Phải cấu hình chủ đề blueprint hoặc thêm ít nhất 1 câu hỏi trước khi xuất bản.'])
                ->withInput();
        }

        $iconPath = null;
        if ($request->hasFile('icon')) {
            $iconPath = $request->file('icon')->store('exams', 'public');
        }

        $exam = Exam::create([
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
            return back()->withErrors(['exam_topics' => implode(' ', $syncResult['errors'])])->withInput();
        }

        if ($status === ExamStatus::Published && count($syncResult['sync']) === 0) {
            return back()
                ->withErrors(['status' => 'Phải có ít nhất 1 câu hỏi sau khi generate từ blueprint topics.'])
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
        ]);

        $availableQuestions = $this->availableQuestions();

        return view('admin::exams.form', compact('exam', 'availableQuestions'));
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $validated = $this->validatedExam($request);
        $status = ExamStatus::from($validated['status']);
        $examTopicsInput = $validated['exam_topics'] ?? [];

        if ($status === ExamStatus::Published && count($examTopicsInput) === 0 && count($validated['questions'] ?? []) === 0) {
            return back()
                ->withErrors(['status' => 'Phải cấu hình chủ đề blueprint hoặc thêm ít nhất 1 câu hỏi trước khi xuất bản.'])
                ->withInput();
        }

        $data = [
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
                ->withErrors(['status' => 'Phải có ít nhất 1 câu hỏi sau khi generate từ blueprint topics.'])
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
            $counts[$topicId] = $this->eligibleQuestionCount($topicId);
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

    /** @return array<string, mixed> */
    private function validatedExam(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:1',
            'icon' => 'nullable|file|image|max:2048',
            'status' => ['required', 'string', Rule::in(ExamStatus::values())],
            'questions' => 'nullable|array',
            'questions.*' => 'exists:questions,id',
            'exam_topics' => 'nullable|array',
            'exam_topics.*.core_clinical_topic_id' => 'required_with:exam_topics|integer|exists:core_clinical_topics,id',
            'exam_topics.*.question_count' => 'required_with:exam_topics|integer|min:1',
            'exam_topics.*.sort_order' => 'nullable|integer|min:0',
        ]);
    }

    /**
     * @param  array<int, array{core_clinical_topic_id?: mixed, question_count?: mixed, sort_order?: mixed}>  $rows
     */
    private function syncExamTopics(Exam $exam, array $rows): void
    {
        $exam->examTopics()->delete();

        foreach (array_values($rows) as $index => $row) {
            $topicId = (int) ($row['core_clinical_topic_id'] ?? 0);
            if ($topicId <= 0) {
                continue;
            }

            ExamTopic::query()->create([
                'exam_id' => $exam->id,
                'core_clinical_topic_id' => $topicId,
                'question_count' => max(1, (int) ($row['question_count'] ?? 1)),
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

        foreach ($exam->examTopics as $examTopic) {
            $needed = $examTopic->question_count;
            $topicName = $examTopic->coreClinicalTopic?->name ?? ('#'.$examTopic->core_clinical_topic_id);

            $questionIds = Question::query()
                ->where('status', QuestionStatus::Private)
                ->where('exam_flag', true)
                ->whereHas('coreClinicalTopics', fn ($query) => $query->where('core_clinical_topics.id', $examTopic->core_clinical_topic_id))
                ->whereNotIn('id', $usedQuestionIds)
                ->orderByDesc('created_at')
                ->limit($needed)
                ->pluck('id');

            if ($questionIds->count() < $needed) {
                $missing = $needed - $questionIds->count();
                $available = $this->eligibleQuestionCount($examTopic->core_clinical_topic_id);
                $errors[] = sprintf(
                    '%s cần %d câu nhưng chỉ có %d eligible (thiếu %d).',
                    $topicName,
                    $needed,
                    $available,
                    $missing,
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

        return ['sync' => $syncData, 'errors' => $errors];
    }

    private function eligibleQuestionCount(int $coreClinicalTopicId): int
    {
        return Question::query()
            ->where('status', QuestionStatus::Private)
            ->where('exam_flag', true)
            ->whereHas('coreClinicalTopics', fn ($query) => $query->where('core_clinical_topics.id', $coreClinicalTopicId))
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
