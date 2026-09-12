<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\QuestionBank\Actions\CreateQuestionSessionAction;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Http\Requests\CreateQuestionSessionRequest;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\OrganSystem;
use Modules\QuestionBank\Models\Subject;
use Modules\QuestionBank\Services\SessionQuestionSelector;
use Modules\QuestionBank\Support\QuestionFilterBuilder;
use RuntimeException;

/** Custom Q-Bank session builder and create endpoint. */
final class CustomSessionController extends Controller
{
    public function __construct(
        private readonly CreateQuestionSessionAction $createSession,
        private readonly SessionQuestionSelector $selector,
    ) {}

    public function create(\Illuminate\Http\Request $request): View
    {
        $userId = $request->user() ? (int) $request->user()->getKey() : 0;
        $bookmarkFolders = $userId > 0
            ? \Modules\Personalization\Models\BookmarkFolder::query()
                ->where('user_id', $userId)
                ->withCount('items')
                ->orderByDesc('id')
                ->get()
            : collect();

        $exams = Blueprint::query()
            ->where('status', TaxonomyStatus::Active)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'description']);

        $blueprintScopes = app(QuestionFilterBuilder::class)
            ->taxonomyScopesForBlueprints($exams->pluck('id')->all());

        return view('questionbank::custom-session', [
            'subjects' => Subject::query()->where('status', TaxonomyStatus::Active)->orderBy('sort_order')->orderBy('name')->get(),
            'organSystems' => OrganSystem::query()->where('status', TaxonomyStatus::Active)->orderBy('sort_order')->orderBy('name')->get(),
            'exams' => $exams
                ->map(fn (Blueprint $blueprint): array => [
                    'id' => (int) $blueprint->id,
                    'title' => $blueprint->name,
                    'hint' => filled($blueprint->description)
                        ? (string) $blueprint->description
                        : 'Ma trận đề thi — lọc câu hỏi theo danh mục đã map.',
                    'icon' => match ($blueprint->code) {
                        'medical_practice_licensing_exam' => 'stethoscope',
                        default => 'assignment',
                    },
                ])
                ->values()
                ->all(),
            'blueprintScopes' => $blueprintScopes,
            'bookmarkFolders' => $bookmarkFolders,
        ]);
    }

    public function store(CreateQuestionSessionRequest $request): RedirectResponse
    {
        try {
            $session = $this->createSession->handle($request->user(), $request->toData());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['filters' => $exception->getMessage()]);
        }

        $route = $session->mode === \Modules\QuestionBank\Enums\SessionMode::Exam
            ? 'exam.session'
            : 'qbank.session';

        return redirect()
            ->route($route, $session)
            ->with('status', 'Đã tạo phiên luyện tập.');
    }

    public function count(CreateQuestionSessionRequest $request): JsonResponse
    {
        $count = $this->selector->countForSession($request->user(), $request->toData());

        return ApiResponse::item(['count' => $count]);
    }
}
