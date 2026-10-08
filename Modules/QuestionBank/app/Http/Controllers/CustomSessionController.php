<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Personalization\Models\BookmarkFolder;
use Modules\QuestionBank\Actions\CreateQuestionSessionAction;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Http\Requests\CreateQuestionSessionRequest;
use Modules\QuestionBank\Models\ExamCatalog;
use Modules\QuestionBank\Models\OrganSystem;
use Modules\QuestionBank\Models\Subject;
use Modules\QuestionBank\Services\SessionQuestionSelector;
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
                ->orderBy('name')
                ->get()
            : collect();
        $restoreBuilderPreferences = $request->query->count() === 0
            && ! $request->session()->has('_old_input');
        $legacyBuilderPreferences = $restoreBuilderPreferences
            ? $request->session()->pull("qbank.session_builder_preferences.{$userId}", [])
            : [];
        $legacyBuilderPreferences = is_array($legacyBuilderPreferences) ? $legacyBuilderPreferences : [];

        $professionId = $request->user()?->learnerProfile?->profession_id;
        $professionId = $professionId !== null ? (int) $professionId : null;

        $exams = $professionId === null
            ? collect()
            : ExamCatalog::query()
                ->where('status', TaxonomyStatus::Active)
                ->whereHas(
                    'professions',
                    fn ($professions) => $professions->where('professions.id', $professionId),
                )
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'description', 'blueprint_id']);

        return view('questionbank::custom-session', [
            'subjects' => Subject::query()->where('status', TaxonomyStatus::Active)->orderBy('name')->get(),
            'organSystems' => OrganSystem::query()->where('status', TaxonomyStatus::Active)->orderBy('name')->get(),
            'needsProfession' => $professionId === null,
            'exams' => $exams
                ->map(fn (ExamCatalog $catalog): array => [
                    'id' => (int) $catalog->id,
                    'title' => $catalog->name,
                    'hint' => filled($catalog->description)
                        ? (string) $catalog->description
                        : 'Chỉ gồm câu đã gắn kỳ thi này và đúng chức danh.',
                    'icon' => 'assignment',
                ])
                ->values()
                ->all(),
            'blueprintScopes' => [],
            'bookmarkFolders' => $bookmarkFolders,
            'legacyBuilderPreferences' => $legacyBuilderPreferences,
            'restoreBuilderPreferences' => $restoreBuilderPreferences,
        ]);
    }

    public function store(CreateQuestionSessionRequest $request): RedirectResponse
    {
        try {
            $session = $this->createSession->handle($request->user(), $request->toData());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['filters' => $exception->getMessage()]);
        }

        return redirect()
            ->route('qbank.session', $session)
            ->with('status', 'Đã tạo phiên luyện tập.');
    }

    public function count(CreateQuestionSessionRequest $request): JsonResponse
    {
        $preview = $this->selector->previewForSession($request->user(), $request->toData());

        return ApiResponse::item([
            'count' => (int) $preview['count'],
            'pickable_count' => (int) $preview['pickable_count'],
            'can_start' => (bool) $preview['can_start'],
            'needs_extra_confirm' => (bool) ($preview['needs_extra_confirm'] ?? false),
            'needs_shortfall_confirm' => (bool) ($preview['needs_shortfall_confirm'] ?? false),
            'message' => $preview['message'],
            'shortfall_message' => $preview['shortfall_message'] ?? null,
            'next_ready_at' => $preview['next_ready_at'],
            'blocked_reason' => $preview['blocked_reason'],
        ]);
    }
}
