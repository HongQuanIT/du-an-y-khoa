<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use App\Support\ScopeFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Personalization\Models\BookmarkFolder;
use Modules\QuestionBank\Actions\CreateQuestionSessionAction;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Http\Requests\CreateQuestionSessionRequest;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
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

    public function create(Request $request): View
    {
        $userId = $request->user() ? (int) $request->user()->getKey() : 0;
        $bookmarkFolders = $userId > 0
            ? BookmarkFolder::query()
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
            'specialties' => MedicalTaxonomyNode::query()->where('node_type', 'specialty')->orderBy('sort_order')->orderBy('name')->get(),
            'systems' => MedicalTaxonomyNode::query()->where('node_type', 'system')->orderBy('sort_order')->orderBy('name')->get(),
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
            'articles' => ScopeFilters::articles(),
            'symptoms' => ScopeFilters::symptoms(),
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

        $route = $session->mode === SessionMode::Exam
            ? 'exam.session'
            : 'qbank.session';

        return redirect()
            ->route($route, $session)
            ->with('status', 'Đã tạo phiên luyện tập.');
    }

    public function count(CreateQuestionSessionRequest $request): JsonResponse
    {
        return ApiResponse::item(
            $this->selector->breakdownForSession($request->user(), $request->toData()),
        );
    }
}
