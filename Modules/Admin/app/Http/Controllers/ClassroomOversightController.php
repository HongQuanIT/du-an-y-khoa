<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\PortalAccess;
use App\Support\Enums\Permission;
use App\Support\Enums\PortalGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Admin\Actions\ApproveClassroomAction;
use Modules\Admin\Actions\ArchiveClassroomAction;
use Modules\Admin\Actions\ForceEndClassroomLiveAction;
use Modules\Admin\Actions\RejectClassroomAction;
use Modules\Classroom\Actions\CreateClassroomAction;
use Modules\Classroom\Actions\ScheduleLiveSessionAction;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Http\Requests\ScheduleSessionRequest;
use Modules\Classroom\Models\Classroom;
use Modules\Exam\Enums\ExamStatus;
use Modules\Exam\Models\Exam;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Support\QuestionFilterBuilder;

final class ClassroomOversightController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorizePermission('classroom_oversight.create_on_behalf');

        $selectedQuestionIds = array_values(array_filter(array_map(
            'strval',
            (array) $request->old('question_ids', []),
        )));

        return view('admin::classrooms.create', [
            'instructors' => User::role(PortalAccess::roleNames(PortalGroup::Instructor))->orderBy('name')->get(['id', 'name', 'email']),
            'purposes' => ClassroomPurpose::teachCases(),
            'visibilities' => ClassroomVisibility::cases(),
            'publishedExams' => Exam::query()
                ->where('status', ExamStatus::Published->value)
                ->withCount('questions')
                ->orderBy('title')
                ->get(['id', 'title', 'description', 'duration_minutes']),
            'selectedQuestions' => Question::query()
                ->with(['lessons:id,name'])
                ->withCount(['feedback as open_feedback_count' => fn ($query) => $query->whereIn('status', [
                    QuestionFeedback::STATUS_PENDING,
                    QuestionFeedback::STATUS_REVIEWING,
                ])])
                ->whereIn('id', $selectedQuestionIds)
                ->get(['id', 'code', 'stem', 'difficulty']),
            'contentCounts' => [
                'questions' => Question::query()->where('status', QuestionStatus::Published->value)->count(),
                'exams' => Exam::query()->where('status', ExamStatus::Published->value)->count(),
                'feedback' => Question::query()->where('status', QuestionStatus::Published->value)
                    ->whereHas('feedback', fn ($query) => $query->whereIn('status', [
                        QuestionFeedback::STATUS_PENDING,
                        QuestionFeedback::STATUS_REVIEWING,
                    ]))->count(),
            ],
            'coreTopicOptions' => CoreClinicalTopic::query()
                ->whereHas(
                    'lessons.questions',
                    fn ($query) => $query->where('status', QuestionStatus::Published->value),
                )
                ->orderBy('name')
                ->get(['id', 'name']),
            'medicalTopicOptions' => Lesson::query()
                ->whereHas('questions', fn ($query) => $query->where('status', QuestionStatus::Published->value))
                ->orderBy('name')
                ->get(['id', 'name']),
            'difficulties' => Difficulty::cases(),
        ]);
    }

    public function contentQuestions(Request $request): JsonResponse
    {
        $this->authorizePermission('classroom_oversight.create_on_behalf');

        $source = $request->string('source')->toString();
        $search = trim($request->string('q')->toString());
        $coreTopicId = $request->integer('core_topic_id');
        $medicalTopicId = $request->integer('medical_topic_id');
        $difficulty = $request->string('difficulty')->toString();
        $filters = app(QuestionFilterBuilder::class);

        $questions = Question::query()
            ->with(['lessons:id,name'])
            ->withCount(['feedback as open_feedback_count' => fn ($query) => $query->whereIn('status', [
                QuestionFeedback::STATUS_PENDING,
                QuestionFeedback::STATUS_REVIEWING,
            ])])
            ->where('status', QuestionStatus::Published->value)
            ->when($coreTopicId > 0, fn ($query) => $filters->whereMatchesCoreClinicalTopic($query, $coreTopicId))
            ->when($medicalTopicId > 0, fn ($query) => $query->whereHas(
                'lessons',
                fn ($topics) => $topics->where('lessons.id', $medicalTopicId),
            ))
            ->when(in_array($difficulty, Difficulty::values(), true), fn ($query) => $query->where('difficulty', $difficulty))
            ->when($source === 'feedback', fn ($query) => $query->whereHas(
                'feedback',
                fn ($feedback) => $feedback->whereIn('status', [
                    QuestionFeedback::STATUS_PENDING,
                    QuestionFeedback::STATUS_REVIEWING,
                ]),
            ))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($questions) use ($search): void {
                    $questions->where('stem', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('lessons', fn ($topics) => $topics->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => $questions->getCollection()->map(fn (Question $question): array => [
                'id' => (string) $question->getKey(),
                'code' => $question->code,
                'text' => trim(strip_tags(html_entity_decode($question->stem, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                'topic' => $question->lessons->pluck('name')->join(', ') ?: 'Tổng hợp',
                'core_topic' => $question->inferredCoreClinicalTopics()->pluck('name')->join(', '),
                'difficulty' => $question->difficulty->label(),
                'feedback_count' => (int) ($question->open_feedback_count ?? 0),
                'edit_url' => route('admin.questions.edit', $question),
            ])->values(),
            'meta' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'total' => $questions->total(),
            ],
        ]);
    }

    public function store(
        Request $request,
        CreateClassroomAction $create,
        ApproveClassroomAction $approve,
        ScheduleLiveSessionAction $schedule,
    ): RedirectResponse {
        $this->authorizePermission('classroom_oversight.create_on_behalf');
        $data = $request->validate([
            'host_user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'purpose' => ['required', Rule::in(array_map(fn (ClassroomPurpose $p) => $p->value, ClassroomPurpose::teachCases()))],
            'visibility' => ['required', Rule::enum(ClassroomVisibility::class)],
            'max_members' => ['nullable', 'integer', 'min:2', 'max:5000'],
            'content_source' => ['nullable', Rule::in(['none', 'questions', 'exam', 'feedback'])],
            'session_title' => [
                'nullable',
                Rule::requiredIf(fn (): bool => in_array($request->input('content_source'), ['questions', 'exam', 'feedback'], true)),
                'string',
                'max:200',
            ],
            'scheduled_at' => ['nullable', 'date'],
            'expected_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'exam_id' => [
                'nullable',
                'required_if:content_source,exam',
                'integer',
                Rule::exists('exams', 'id')->where('status', ExamStatus::Published->value),
            ],
            'question_ids' => [
                'nullable',
                Rule::requiredIf(fn (): bool => in_array($request->input('content_source'), ['questions', 'feedback'], true)),
                'array',
                'min:1',
            ],
            'question_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('questions', 'id')->where('status', QuestionStatus::Published->value),
            ],
        ]);
        $host = User::findOrFail($data['host_user_id']);
        abort_unless(PortalAccess::allows($host, PortalGroup::Instructor), 422, 'Host phải là giảng viên.');

        $source = $data['content_source'] ?? 'none';
        $questionIds = array_values(array_unique(array_map('strval', $data['question_ids'] ?? [])));
        if ($source === 'feedback') {
            $validFeedbackQuestions = Question::query()
                ->whereIn('id', $questionIds)
                ->whereHas('feedback', fn ($query) => $query->whereIn('status', [
                    QuestionFeedback::STATUS_PENDING,
                    QuestionFeedback::STATUS_REVIEWING,
                ]))
                ->count();

            if ($validFeedbackQuestions !== count($questionIds)) {
                throw ValidationException::withMessages([
                    'question_ids' => 'Chỉ được chọn câu hỏi còn feedback cần xử lý.',
                ]);
            }
        }

        if ($source === 'exam') {
            $data['purpose'] = ClassroomPurpose::ExamReview->value;
        } elseif (in_array($source, ['questions', 'feedback'], true)) {
            $data['purpose'] = ClassroomPurpose::FeedbackReview->value;
        }

        // Lớp do chính Admin tạo và duyệt ngay không cần thông báo chờ duyệt cho các Admin khác.
        $classroom = DB::transaction(function () use ($create, $host, $data, $approve, $schedule, $source, $questionIds): Classroom {
            $classroom = $create->handle($host, $data, notifyAdmins: false);
            $classroom->forceFill([
                'meta' => array_merge($classroom->meta ?? [], ['content_source' => $source]),
            ])->save();
            if ($this->actor()->can('classroom_oversight.approve')) {
                $approve->handle($this->actor(), $classroom);
            }

            if ($source !== 'none') {
                $sessionData = [
                    'title' => $data['session_title'],
                    'scheduled_at' => $data['scheduled_at'] ?? null,
                    'expected_duration_seconds' => (int) ($data['expected_duration_minutes'] ?? 60) * 60,
                ];

                if ($source === 'exam') {
                    $exam = Exam::query()
                        ->with(['questions' => fn ($query) => $query->where('status', QuestionStatus::Published->value)])
                        ->findOrFail($data['exam_id']);
                    $examQuestionIds = array_values(array_map('strval', $exam->questions->modelKeys()));
                    $sessionData['linked_exam_id'] = $exam->getKey();
                    $sessionData['question_set'] = [
                        'source' => 'exam',
                        'exam_id' => $exam->getKey(),
                        'question_ids' => $examQuestionIds,
                    ];
                } else {
                    $sessionData['question_set'] = [
                        'source' => $source === 'feedback' ? 'feedback' : 'manual',
                        'question_ids' => $questionIds,
                    ];
                }

                $schedule->handle($classroom, $sessionData);
            }

            return $classroom;
        });

        return redirect()->route('admin.classrooms.show', $classroom)->with(
            'status',
            $source === 'none'
                ? 'Đã tạo lớp cho giảng viên.'
                : 'Đã tạo lớp và nạp nội dung cho buổi live đầu tiên.',
        );
    }

    public function index(Request $request): View
    {
        $this->authorizePermission('classroom_oversight.view');

        $query = Classroom::query()
            ->with(['host', 'liveSession'])
            ->withCount([
                'activeMembers',
                'sessions as live_sessions_count' => fn ($q) => $q->where('status', LiveSessionStatus::Live->value),
            ])
            ->latest('id');

        if ($search = trim((string) $request->query('q', ''))) {
            $term = '%'.addcslashes($search, '\\%_').'%';

            $query->where(function ($builder) use ($term): void {
                $builder->where('title', 'like', $term)
                    ->orWhere('join_code', 'like', $term)
                    ->orWhere('uuid', 'like', $term)
                    ->orWhereHas('host', fn ($host) => $host->where('name', 'like', $term));
            });
        }

        $availableStatuses = array_values(array_filter(
            ClassroomStatus::values(),
            static fn (string $status): bool => $status !== ClassroomStatus::Draft->value,
        ));
        $statuses = array_values(array_intersect(
            array_map('strval', (array) $request->query('status', [])),
            $availableStatuses,
        ));
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $purposes = array_values(array_intersect(
            array_map('strval', (array) $request->query('purpose', [])),
            ClassroomPurpose::values(),
        ));
        if ($purposes !== []) {
            $query->whereIn('purpose', $purposes);
        }

        $hostIds = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            (array) $request->query('host_id', []),
        )));
        if ($hostIds !== []) {
            $query->whereIn('host_user_id', $hostIds);
        }

        $classrooms = $query->paginate(20)->withQueryString();

        $pendingCount = Classroom::query()
            ->where('status', ClassroomStatus::PendingApproval)
            ->count();

        return view('admin::classrooms.index', [
            'classrooms' => $classrooms,
            'pendingCount' => $pendingCount,
            'statuses' => array_values(array_filter(
                ClassroomStatus::cases(),
                static fn (ClassroomStatus $status): bool => $status !== ClassroomStatus::Draft,
            )),
            'purposes' => ClassroomPurpose::cases(),
            'hosts' => User::query()
                ->whereIn('id', Classroom::query()->select('host_user_id')->whereNotNull('host_user_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'filters' => [
                'q' => $search,
                'status' => $statuses,
                'purpose' => $purposes,
                'host_id' => array_map('strval', $hostIds),
            ],
        ]);
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizePermission('classroom_oversight.update');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['required', Rule::enum(ClassroomVisibility::class)],
            'max_members' => ['nullable', 'integer', 'min:2', 'max:5000'],
        ]);

        $classroom->update($data);

        return back()->with('status', 'Đã cập nhật lớp học.');
    }

    public function show(Classroom $classroom): View
    {
        $this->authorizePermission('classroom_oversight.view');

        $classroom->load([
            'host',
            'activeMembers.user',
            'sessions' => fn ($query) => $query
                ->with('recordings')
                ->latest('scheduled_at')
                ->limit(20),
        ]);

        return view('admin::classrooms.show', [
            'classroom' => $classroom,
        ]);
    }

    public function scheduleLive(
        ScheduleSessionRequest $request,
        Classroom $classroom,
        ScheduleLiveSessionAction $action,
    ): RedirectResponse {
        $this->authorizePermission('classroom_oversight.schedule');
        $session = $action->handle($classroom, $request->sessionPayload());

        return back()->with('status', 'Đã tạo phòng live: '.$session->title);
    }

    public function forceEnd(Classroom $classroom, ForceEndClassroomLiveAction $action): RedirectResponse
    {
        $this->authorizePermission('classroom_oversight.view');

        $ended = $action->handle($this->actor(), $classroom);

        if ($ended === null) {
            return back()->with('status', 'Lớp không có buổi live đang chạy.');
        }

        return back()->with('status', 'Đã force-end buổi live.');
    }

    public function approve(Classroom $classroom, ApproveClassroomAction $action): RedirectResponse
    {
        $this->authorizePermission('classroom_oversight.approve');

        $action->handle($this->actor(), $classroom);

        return back()->with('status', 'Đã duyệt lớp — hiển thị cho học viên.');
    }

    public function reject(Classroom $classroom, RejectClassroomAction $action): RedirectResponse
    {
        $this->authorizePermission('classroom_oversight.reject');

        $action->handle($this->actor(), $classroom);

        return back()->with('status', 'Đã từ chối lớp học.');
    }

    public function archive(Classroom $classroom, ArchiveClassroomAction $action): RedirectResponse
    {
        $this->authorizePermission('classroom_oversight.archive');

        $action->handle($this->actor(), $classroom);

        return back()->with('status', 'Đã lưu trữ lớp học.');
    }

    private function authorizePermission(string|Permission ...$permissions): void
    {
        $abilities = array_map(
            static fn (string|Permission $permission): string => $permission instanceof Permission
                ? $permission->value
                : $permission,
            $permissions,
        );

        abort_unless($this->actor()->canAny($abilities), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
