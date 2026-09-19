<?php

declare(strict_types=1);

namespace Modules\Personalization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Personalization\Actions\CreateBookmarkFolderAction;
use Modules\Personalization\Actions\GetBookmarkFoldersAction;
use Modules\Personalization\Actions\ToggleBookmarkFolderItemAction;
use Modules\Personalization\Models\BookmarkFolder;
use Modules\Personalization\Models\BookmarkFolderItem;

final class BookmarkFolderController extends Controller
{
    public function index(
        Request $request,
        GetBookmarkFoldersAction $getFolders,
    ): JsonResponse {
        $user = $request->user();
        assert($user !== null);

        $questionId = (string) $request->query('question_id', '');

        $result = $getFolders->handle($user, $questionId);

        return ApiResponse::item($result);
    }

    public function store(
        Request $request,
        CreateBookmarkFolderAction $createFolder,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'question_id' => ['nullable'],
        ]);

        $user = $request->user();
        assert($user !== null);

        $questionId = isset($validated['question_id']) && $validated['question_id'] !== ''
            ? (string) $validated['question_id']
            : null;

        $result = $createFolder->handle(
            $user,
            (string) $validated['name'],
            $questionId,
        );

        return ApiResponse::item($result);
    }

    public function toggle(
        Request $request,
        BookmarkFolder $folder,
        ToggleBookmarkFolderItemAction $toggleFolder,
    ): JsonResponse {
        $validated = $request->validate([
            'question_id' => ['required'],
            'in_folder' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        assert($user !== null);

        abort_if((int) $folder->user_id !== (int) $user->getKey(), 403);

        $questionId = (string) $validated['question_id'];
        $requestedInFolder = isset($validated['in_folder']) ? (bool) $validated['in_folder'] : null;
        $willBeInFolder = $requestedInFolder;

        if ($willBeInFolder === null) {
            $exists = BookmarkFolderItem::query()
                ->where('folder_id', $folder->id)
                ->where('question_id', $questionId)
                ->exists();
            $willBeInFolder = ! $exists;
        }

        if ($willBeInFolder) {
            abort_unless($user->can('bookmark.create'), 403);
        } else {
            abort_unless($user->can('bookmark.delete'), 403);
        }

        $result = $toggleFolder->handle(
            $user,
            $folder,
            $questionId,
            $requestedInFolder,
        );

        return ApiResponse::item($result);
    }

    public function destroy(
        Request $request,
        BookmarkFolder $folder,
        \Modules\Personalization\Actions\DeleteBookmarkFolderAction $deleteFolder,
    ): \Illuminate\Http\RedirectResponse {
        $user = $request->user();
        assert($user !== null);

        abort_if((int) $folder->user_id !== (int) $user->getKey(), 403);

        $name = $folder->name;
        $deleteFolder->handle($user, $folder);

        return redirect()
            ->route('qbank.bookmarks')
            ->with('status', 'Đã xóa bộ sưu tập "' . $name . '".');
    }
}
