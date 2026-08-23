<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Html\SafeHtml;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Personalization\Models\Bookmark;
use Modules\Personalization\Models\BookmarkFolder;
use Modules\Personalization\Models\BookmarkFolderItem;
use Modules\QuestionBank\Actions\CreateSessionFromBookmarksAction;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use RuntimeException;

/** Learner-owned list of saved Q-Bank question collections & folders. */
final class QuestionBookmarkPageController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->user()->getKey();

        // Ensure default folder exists for user if no folders exist
        $hasFolders = BookmarkFolder::query()->where('user_id', $userId)->exists();
        if (! $hasFolders) {
            BookmarkFolder::query()->create([
                'user_id' => $userId,
                'name' => 'câu hỏi lưu',
            ]);
        }

        $folders = BookmarkFolder::query()
            ->where('user_id', $userId)
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(3, ['*'], 'folder_page')
            ->withQueryString();

        $totalCount = Bookmark::query()
            ->where('user_id', $userId)
            ->where('bookmarkable_type', Bookmark::TYPE_QUESTION)
            ->count();

        $activeFolderId = $request->query('folder_id');
        $activeFolder = null;

        if (filled($activeFolderId)) {
            $activeFolder = BookmarkFolder::query()
                ->where('user_id', $userId)
                ->where('id', (int) $activeFolderId)
                ->first();
        }

        if ($activeFolder !== null) {
            $itemQuestionIds = BookmarkFolderItem::query()
                ->where('folder_id', $activeFolder->id)
                ->pluck('question_id')
                ->all();

            $bookmarks = Bookmark::query()
                ->where('user_id', $userId)
                ->where('bookmarkable_type', Bookmark::TYPE_QUESTION)
                ->whereIn('bookmarkable_id', $itemQuestionIds)
                ->latest()
                ->paginate(15)
                ->withQueryString();
        } else {
            $bookmarks = Bookmark::query()
                ->where('user_id', $userId)
                ->where('bookmarkable_type', Bookmark::TYPE_QUESTION)
                ->latest()
                ->paginate(15)
                ->withQueryString();
        }

        $questionIds = $bookmarks->getCollection()
            ->pluck('bookmarkable_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $questions = Question::query()
            ->with(['topic:id,name', 'topics:id,name', 'options' => fn ($query) => $query->orderBy('order')->orderBy('id')])
            ->whereIn('id', $questionIds)
            ->get()
            ->keyBy(static fn (Question $question): string => (string) $question->getKey());

        $items = $bookmarks->getCollection()->map(function (Bookmark $bookmark) use ($questions): array {
            $question = $questions->get((string) $bookmark->bookmarkable_id);
            $available = $question instanceof Question
                && $question->status === QuestionStatus::Published;
            $preview = $question instanceof Question
                ? Str::limit(SafeHtml::plainText((string) $question->stem), 180)
                : 'Câu hỏi không còn khả dụng.';

            $options = $question instanceof Question
                ? $question->options->map(static fn ($option): array => [
                    'label' => (string) $option->label,
                    'content' => SafeHtml::forDisplay((string) $option->content),
                    'correct' => (bool) $option->is_correct,
                    'explanation' => SafeHtml::forDisplay((string) ($option->explanation ?? '')),
                ])->values()->all()
                : [];

            return [
                'id' => (string) $bookmark->bookmarkable_id,
                'preview' => $preview,
                'stem_html' => $question instanceof Question
                    ? SafeHtml::forDisplay((string) $question->stem)
                    : '',
                'explanation' => $question instanceof Question
                    ? SafeHtml::forDisplay((string) ($question->explanation ?? ''))
                    : '',
                'options' => $options,
                'topic' => $question?->topics->pluck('name')->join(', ') ?: $question?->topic?->name,
                'topics' => $question?->topics->pluck('name')->values()->all() ?? [],
                'difficulty' => $question?->difficulty?->label(),
                'saved_at' => $bookmark->created_at?->format('d/m/Y H:i') ?? '—',
                'available' => $available,
            ];
        });

        $bookmarks->setCollection($items);

        return view('questionbank::bookmarks', [
            'folders' => $folders,
            'activeFolder' => $activeFolder,
            'bookmarks' => $bookmarks,
            'totalCount' => $totalCount,
        ]);
    }

    public function destroy(Request $request, string $question): RedirectResponse
    {
        $userId = (int) $request->user()->getKey();
        $folderId = $request->query('folder_id');

        if (filled($folderId)) {
            BookmarkFolderItem::query()
                ->where('folder_id', (int) $folderId)
                ->where('question_id', $question)
                ->delete();

            // Check if question remains in any folder
            $isInAnyFolder = BookmarkFolderItem::query()
                ->whereHas('folder', fn ($q) => $q->where('user_id', $userId))
                ->where('question_id', $question)
                ->exists();

            if (! $isInAnyFolder) {
                Bookmark::query()
                    ->where('user_id', $userId)
                    ->where('bookmarkable_type', Bookmark::TYPE_QUESTION)
                    ->where('bookmarkable_id', $question)
                    ->delete();
            }
        } else {
            // Remove completely from all folders and bookmarks
            BookmarkFolderItem::query()
                ->whereHas('folder', fn ($q) => $q->where('user_id', $userId))
                ->where('question_id', $question)
                ->delete();

            Bookmark::query()
                ->where('user_id', $userId)
                ->where('bookmarkable_type', Bookmark::TYPE_QUESTION)
                ->where('bookmarkable_id', $question)
                ->delete();
        }

        return back()->with('status', 'Đã bỏ lưu câu hỏi.');
    }

    public function startSession(
        Request $request,
        CreateSessionFromBookmarksAction $createSession,
    ): RedirectResponse {
        $validated = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['string'],
        ], [
            'question_ids.required' => 'Hãy chọn ít nhất một câu hỏi đã lưu.',
            'question_ids.min' => 'Hãy chọn ít nhất một câu hỏi đã lưu.',
        ]);

        try {
            $session = $createSession->handle(
                $request->user(),
                array_values($validated['question_ids']),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'question_ids' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('qbank.session', $session)
            ->with('status', 'Đã tạo phiên từ câu hỏi đã lưu.');
    }
}
