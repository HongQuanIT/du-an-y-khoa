<?php

declare(strict_types=1);

namespace Modules\Personalization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Personalization\Actions\DeleteNoteAction;
use Modules\Personalization\Actions\ListNotesAction;
use Modules\Personalization\Actions\UpsertNoteAction;
use Modules\Personalization\Models\Note;

final class NoteController extends Controller
{
    public function index(Request $request, ListNotesAction $listNotes): View|JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        // Backward-compatible aliases from older query params.
        $view = $request->string('view')->toString()
            ?: match ($request->string('group')->toString()) {
                'lesson' => 'lesson',
                'notebook', 'free' => 'notebook',
                default => 'subject',
            };

        if (! in_array($view, ['subject', 'lesson', 'notebook'], true)) {
            $view = 'subject';
        }

        $filters = [
            'q' => $request->string('q')->toString() ?: null,
            'view' => $view,
            'per_page' => (int) $request->input('per_page', 20),
        ];

        $result = $listNotes->handle($user, $filters);

        if ($request->expectsJson()) {
            if ($result['mode'] === 'flat') {
                return ApiResponse::paginated($result['notes']);
            }

            return ApiResponse::item([
                'mode' => 'grouped',
                'total' => $result['total'],
                'groups' => $result['groups'],
            ]);
        }

        return view('personalization::notes.index', [
            'result' => $result,
            'filters' => $filters,
            'viewOptions' => [
                'subject' => 'Theo môn',
                'lesson' => 'Theo bài',
                'notebook' => 'Sổ tay',
            ],
            'colors' => Note::COLORS,
        ]);
    }

    public function store(Request $request, UpsertNoteAction $upsert): JsonResponse
    {
        $validated = $this->validatedPayload($request);
        $user = $request->user();
        assert($user !== null);

        try {
            $result = $upsert->handle(
                $user,
                $validated['body_html'] ?? null,
                $validated['body'] ?? null,
                $validated['notable_type'] ?? null,
                isset($validated['notable_id']) ? (string) $validated['notable_id'] : null,
                $validated['color'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error('NOTE_INVALID', $e->getMessage(), 422);
        }

        if ($result['deleted'] || $result['note'] === null) {
            return ApiResponse::item(['deleted' => true]);
        }

        return ApiResponse::item($this->noteItem($result['note']), 201);
    }

    public function update(Request $request, Note $note, UpsertNoteAction $upsert): JsonResponse
    {
        abort_unless((int) $note->user_id === (int) $request->user()?->getAuthIdentifier(), 403);

        $validated = $this->validatedPayload($request, updating: true);
        $user = $request->user();
        assert($user !== null);

        try {
            $result = $upsert->handle(
                $user,
                $validated['body_html'] ?? null,
                $validated['body'] ?? null,
                $note->notable_type,
                $note->notable_id,
                array_key_exists('color', $validated) ? ($validated['color'] ?? '') : $note->color,
                $note,
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error('NOTE_INVALID', $e->getMessage(), 422);
        }

        if ($result['deleted'] || $result['note'] === null) {
            return ApiResponse::item(['deleted' => true]);
        }

        return ApiResponse::item($this->noteItem($result['note']));
    }

    public function destroy(Request $request, Note $note, DeleteNoteAction $delete): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        $delete->handle($user, $note);

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'body' => [$updating ? 'sometimes' : 'nullable', 'string', 'max:5000'],
            'body_html' => [$updating ? 'sometimes' : 'nullable', 'string', 'max:20000'],
            'notable_type' => ['nullable', 'string', 'in:'.Note::TYPE_QUESTION],
            'notable_id' => ['nullable', 'string', 'max:64', 'required_with:notable_type'],
            'color' => ['nullable', 'string', 'in:'.implode(',', Note::COLORS)],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function noteItem(Note $note): array
    {
        return [
            'id' => $note->id,
            'notable_type' => $note->notable_type,
            'notable_id' => $note->notable_id,
            'type_label' => $note->isFree() ? 'Sổ tay' : 'Câu hỏi',
            'body' => $note->body,
            'body_html' => $note->body_html,
            'color' => $note->color,
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }
}
