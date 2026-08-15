<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Admin\Actions\SaveAdminQuestionAction;
use Modules\Admin\Actions\TransitionQuestionStatusAction;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\Topic;

final class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::QuestionView);

        $query = Question::query()->with('topic')->latest('updated_at');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where('stem', 'like', "%{$search}%");
        }

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        if ($difficulty = $request->query('difficulty')) {
            $query->where('difficulty', (string) $difficulty);
        }

        if ($topicId = $request->query('topic_id')) {
            $query->where('topic_id', (int) $topicId);
        }

        return view('admin::questions.index', [
            'questions' => $query->paginate(20)->withQueryString(),
            'statuses' => QuestionStatus::cases(),
            'difficulties' => Difficulty::cases(),
            'topics' => Topic::query()->orderBy('name')->get(['id', 'name', 'type']),
            'filters' => [
                'q' => $search,
                'status' => $request->query('status'),
                'difficulty' => $request->query('difficulty'),
                'topic_id' => $request->query('topic_id'),
            ],
            'canCreate' => $this->actor()->can(Permission::QuestionCreate->value),
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission(Permission::QuestionCreate);

        return view('admin::questions.form', $this->formData(new Question([
            'status' => QuestionStatus::Draft,
            'difficulty' => Difficulty::Medium,
            'is_free' => false,
        ])));
    }

    public function store(Request $request, SaveAdminQuestionAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::QuestionCreate);

        $question = $action->handle($this->actor(), null, $this->validatedPayload($request));

        return redirect()
            ->route('admin.questions.edit', $question)
            ->with('status', 'Đã tạo câu hỏi nháp.');
    }

    public function edit(Question $question): View
    {
        $this->authorizePermission(Permission::QuestionView);

        $question->load(['options' => fn ($q) => $q->orderBy('order'), 'topic']);

        return view('admin::questions.form', $this->formData($question));
    }

    public function update(Request $request, Question $question, SaveAdminQuestionAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::QuestionUpdate);

        $action->handle($this->actor(), $question, $this->validatedPayload($request));

        return back()->with('status', 'Đã lưu câu hỏi.');
    }

    public function transition(
        Request $request,
        Question $question,
        TransitionQuestionStatusAction $action,
    ): RedirectResponse {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(QuestionStatus::values())],
        ]);

        $action->handle($this->actor(), $question, QuestionStatus::from($data['status']));

        return back()->with('status', 'Đã cập nhật trạng thái: '.QuestionStatus::from($data['status'])->label());
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Question $question): array
    {
        return [
            'question' => $question,
            'statuses' => QuestionStatus::cases(),
            'difficulties' => Difficulty::cases(),
            'topics' => Topic::query()->orderBy('name')->get(['id', 'name', 'type']),
            'canUpdate' => $this->actor()->can(Permission::QuestionUpdate->value)
                || ($question->exists === false && $this->actor()->can(Permission::QuestionCreate->value)),
            'canPublish' => $this->actor()->can(Permission::QuestionPublish->value),
        ];
    }

    /**
     * @return array{
     *     stem: string,
     *     stem_image_path: ?string,
     *     explanation: ?string,
     *     key_info: array<int, string>,
     *     attending_tip: ?string,
     *     difficulty: string,
     *     topic_id: int,
     *     is_free: bool,
     *     options: list<array{id?: int|null, content: string, is_correct: bool, explanation?: ?string}>
     * }
     */
    private function validatedPayload(Request $request): array
    {
        $data = $request->validate([
            'stem' => ['required', 'string'],
            'stem_image_path' => ['nullable', 'string', 'max:1024'],
            'explanation' => ['nullable', 'string'],
            'key_info' => ['nullable', 'string'],
            'attending_tip' => ['nullable', 'string'],
            'difficulty' => ['required', Rule::in(Difficulty::values())],
            'topic_id' => ['required', 'integer', 'exists:topics,id'],
            'is_free' => ['sometimes', 'boolean'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.content' => ['required', 'string'],
            'options.*.is_correct' => ['sometimes', 'boolean'],
            'options.*.explanation' => ['nullable', 'string'],
        ], [
            'stem.required' => 'Vui lòng nhập nội dung câu hỏi.',
            'topic_id.required' => 'Vui lòng chọn chủ đề.',
            'options.required' => 'Vui lòng thêm đáp án.',
            'options.min' => 'Cần ít nhất 2 đáp án.',
            'options.*.content.required' => 'Nội dung đáp án không được để trống.',
        ]);

        $options = [];

        foreach ($data['options'] as $row) {
            $rawCorrect = $row['is_correct'] ?? false;
            $options[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'content' => $row['content'],
                'is_correct' => $rawCorrect === true || $rawCorrect === 1 || $rawCorrect === '1',
                'explanation' => $row['explanation'] ?? null,
            ];
        }

        return [
            'stem' => $data['stem'],
            'stem_image_path' => $data['stem_image_path'] ?? null,
            'explanation' => $data['explanation'] ?? null,
            'key_info' => $this->parseKeyInfo($data['key_info'] ?? null),
            'attending_tip' => $data['attending_tip'] ?? null,
            'difficulty' => $data['difficulty'],
            'topic_id' => (int) $data['topic_id'],
            'is_free' => $request->boolean('is_free'),
            'options' => $options,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function parseKeyInfo(?string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/u', (string) $raw) ?: [];

        return collect($lines)
            ->map(fn (string $line): string => trim(strip_tags($line)))
            ->filter(fn (string $line): bool => $line !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function authorizePermission(Permission $permission): void
    {
        abort_unless($this->actor()->can($permission->value), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
