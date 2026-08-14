<?php

declare(strict_types=1);

namespace Modules\Personalization\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Personalization\Models\BookmarkFolder;
use Modules\Personalization\Models\BookmarkFolderItem;

final class GetBookmarkFoldersAction
{
    use AsAction;

    /**
     * @return array{folders: list<array{id: int, name: string, in_folder: bool}>, bookmarked: bool}
     */
    public function handle(User $user, string $questionId): array
    {
        $userId = (int) $user->getKey();

        // Ensure default folder exists
        $hasFolders = BookmarkFolder::query()->where('user_id', $userId)->exists();
        if (! $hasFolders) {
            BookmarkFolder::query()->create([
                'user_id' => $userId,
                'name' => 'câu hỏi lưu',
            ]);
        }

        $folders = BookmarkFolder::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get();

        $activeFolderIds = BookmarkFolderItem::query()
            ->whereIn('folder_id', $folders->pluck('id'))
            ->where('question_id', $questionId)
            ->pluck('folder_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $activeLookup = array_fill_keys($activeFolderIds, true);

        $folderList = $folders->map(fn (BookmarkFolder $folder): array => [
            'id' => (int) $folder->id,
            'name' => (string) $folder->name,
            'in_folder' => isset($activeLookup[(int) $folder->id]),
        ])->values()->all();

        $isBookmarked = count($activeFolderIds) > 0;

        return [
            'folders' => $folderList,
            'bookmarked' => $isBookmarked,
        ];
    }
}
