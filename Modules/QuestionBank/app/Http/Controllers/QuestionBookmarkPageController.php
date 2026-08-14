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
use Modules\QuestionBank\Actions\CreateSessionFromBookmarksAction;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use RuntimeException;

/** Learner-owned list of saved Q-Bank questions. */
final class QuestionBookmarkPageController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) $request->user()->getKey();

        $bookmarks = Bookmark::query()
            ->where('user_id', $userId)
            ->where('bookmarkable_type', Bookmark::TYPE_QUESTION)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $questionIds = $bookmarks->getCollection()
            ->pluck('bookmarkable_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $questions = Question::query()
            ->with(['topic:id,name', 'options' => fn ($query) => $query->orderBy('order')->orderBy('id')])
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
                'topic' => $question?->topic?->name,
                'difficulty' => $question?->difficulty?->label(),
                'saved_at' => $bookmark->created_at?->format('d/m/Y H:i') ?? '—',
                'available' => $available,
            ];
        });

        $bookmarks->setCollection($items);

        return view('questionbank::bookmarks', [
            'bookmarks' => $bookmarks,
        ]);
    }

    public function destroy(Request $request, string $question): RedirectResponse
    {
        Bookmark::query()
            ->where('user_id', $request->user()->getKey())
            ->where('bookmarkable_type', Bookmark::TYPE_QUESTION)
            ->where('bookmarkable_id', $question)
            ->delete();

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
