<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use App\Support\ScopeFilters;
use App\Support\TargetExams;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\QuestionBank\Actions\CreateQuestionSessionAction;
use Modules\QuestionBank\Http\Requests\CreateQuestionSessionRequest;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Services\SessionQuestionSelector;
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

        return view('questionbank::custom-session', [
            'specialties' => MedicalTaxonomyNode::query()->where('node_type', 'specialty')->orderBy('sort_order')->orderBy('name')->get(),
            'systems' => MedicalTaxonomyNode::query()->where('node_type', 'system')->orderBy('sort_order')->orderBy('name')->get(),
            'exams' => TargetExams::selectable(),
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
