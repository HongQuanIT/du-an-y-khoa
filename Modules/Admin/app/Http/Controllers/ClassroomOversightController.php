<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use App\Support\Enums\Role;
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
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionFeedback;

final class ClassroomOversightController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorizePermission(Permission::ClassroomCreateOnBehalf);

        $selectedQuestionIds = array_values(array_filter(array_map(
            'strval',
            (array) $request->old('question_ids', []),
        )));

        return view('admin::classrooms.create', [
            'instructors' => User::role(Role::Instructor->value)->orderBy('name')->get(['id', 'name', 'email']),
            'purposes' => ClassroomPurpose::teachCases(),
            'visibilities' => ClassroomVisibility::cases(),
            'publishedExams' => Exam::query()
                ->where('status', ExamStatus::Published->value)
                ->withCount('questions')
                ->orderBy('title')
                ->get(['id', 'title', 'description', 'duration_minutes']),
            'selectedQuestions' => Question::query()
                ->with(['coreClinicalTopics:id,name', 'medicalTaxonomyNodes:id,name'])
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
                ->whereHas('questions', fn ($query) => $query->where('status', QuestionStatus::Published->value))
                ->orderBy('name')
                ->get(['id', 'name']),
            'medicalTopicOptions' => MedicalTaxonomyNode::query()
                ->whereHas('questions', fn ($query) => $query->where('status', QuestionStatus::Published->value))
                ->orderBy('name')
                ->get(['id', 'name', 'node_type']),
            'difficulties' => Difficulty::cases(),
        ]);
    }

    public function contentQuestions(Request $request): JsonResponse
    {
        $this->authorizePermission(Permission::ClassroomCreateOnBehalf);

        $source = $request->string('source')->toString();
        $search = trim($request->string('q')->toString());
        $coreTopicId = $request->integer('core_topic_id');
        $medicalTopicId = $request->integer('medical_topic_id');
        $difficulty = $request->string('difficulty')->toString();

        $questions = Question::query()
            ->with(['coreClinicalTopics:id,name', 'medicalTaxonomyNodes:id,name'])
            ->withCount(['feedback as open_feedback_count' => fn ($query) => $query->whereIn('status', [
                QuestionFeedback::STATUS_PENDING,
                QuestionFeedback::STATUS_REVIEWING,
            ])])
            ->where('status', QuestionStatus::Published->value)
            ->when($coreTopicId > 0, fn ($query) => $query->whereHas(
                'coreClinicalTopics',
                fn ($topics) => $topics->where('core_clinical_topics.id', $coreTopicId),
            ))
            ->when($medicalTopicId > 0, fn ($query) => $query->whereHas(
                'medicalTaxonomyNodes',
                fn ($topics) => $topics->where('medical_taxonomy_nodes.id', $medicalTopicId),
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
                        ->orWhereHas('medicalTaxonomyNodes', fn ($topics) => $topics->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => $questions->getCollection()->map(fn (Question $question): array => [
                'id' => (string) $question->getKey(),
                'code' => $question->code,
                'text' => trim(strip_tags(html_entity_decode($question->stem, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                'topic' => $question->medicalTaxonomyNodes->pluck('name')->join(', ') ?: 'Tổng hợp',
                'core_topic' => $question->coreClinicalTopics->pluck('name')->join(', '),
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
        $this->authorizePermission(Permission::ClassroomCreateOnBehalf);
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
        abort_unless($host->hasRole(Role::Instructor->value), 422, 'Host phải là giảng viên.');

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
            $approve->handle($this->actor(), $classroom);

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
        $this->authorizePermission(Permission::ClassroomOversee);

        $query = Classroom::query()
            ->with(['host', 'liveSession'])
            ->withCount([
                'activeMembers',
                'sessions as live_sessions_count' => fn ($q) => $q->where('status', LiveSessionStatus::Live->value),
            ])
            ->latest('id');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('join_code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        if ($purpose = $request->query('purpose')) {
            $query->where('purpose', (string) $purpose);
        }

        if ($hostId = $request->query('host_id')) {
            $query->where('host_user_id', (int) $hostId);
        }

        $classrooms = $query->paginate(20)->withQueryString();

        $pendingCount = Classroom::query()
            ->where('status', ClassroomStatus::PendingApproval)
            ->count();

        return view('admin::classrooms.index', [
            'classrooms' => $classrooms,
            'pendingCount' => $pendingCount,
            'statuses' => ClassroomStatus::cases(),
            'purposes' => ClassroomPurpose::cases(),
            'filters' => [
                'q' => $search,
                'status' => $request->query('status'),
                'purpose' => $request->query('purpose'),
                'host_id' => $request->query('host_id'),
            ],
        ]);
    }

    public function show(Classroom $classroom): View
    {
        $this->authorizePermission(Permission::ClassroomOversee);

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
        $this->authorizePermission(Permission::ClassroomOversee);
        $session = $action->handle($classroom, $request->sessionPayload());

        return back()->with('status', 'Đã tạo phòng live: '.$session->title);
    }

    public function forceEnd(Classroom $classroom, ForceEndClassroomLiveAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        $ended = $action->handle($this->actor(), $classroom);

        if ($ended === null) {
            return back()->with('status', 'Lớp không có buổi live đang chạy.');
        }

        return back()->with('status', 'Đã force-end buổi live.');
    }

    public function approve(Classroom $classroom, ApproveClassroomAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        $action->handle($this->actor(), $classroom);

        return back()->with('status', 'Đã duyệt lớp — hiển thị cho học viên.');
    }

    public function reject(Classroom $classroom, RejectClassroomAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        $action->handle($this->actor(), $classroom);

        return back()->with('status', 'Đã từ chối lớp học.');
    }

    public function archive(Classroom $classroom, ArchiveClassroomAction $action): RedirectResponse
    {
        $this->authorizePermission(Permission::ClassroomOversee);

        $action->handle($this->actor(), $classroom);

        return back()->with('status', 'Đã lưu trữ lớp học.');
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
