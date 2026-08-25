<?php

declare(strict_types=1);

namespace Modules\Personalization\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Modules\Personalization\Models\Bookmark;
use Modules\Personalization\Models\BookmarkFolder;
use Modules\Personalization\Models\BookmarkFolderItem;

final class ToggleBookmarkFolderItemAction
{
    use AsAction;

    public function __construct(
        private readonly GetBookmarkFoldersAction $getFolders,
    ) {}

    /**
     * @return array{folders: list<array{id: int, name: string, in_folder: bool}>, bookmarked: bool}
     */
    public function handle(User $user, BookmarkFolder $folder, string $questionId, ?bool $inFolder = null): array
    {
        $userId = (int) $user->getKey();
        assert((int) $folder->user_id === $userId);

        $existing = BookmarkFolderItem::query()
            ->where('folder_id', $folder->id)
            ->where('question_id', $questionId)
            ->first();

        $shouldBeInFolder = $inFolder ?? ($existing === null);

        if ($shouldBeInFolder) {
            if ($existing === null) {
                BookmarkFolderItem::query()->create([
                    'folder_id' => $folder->id,
                    'question_id' => $questionId,
                ]);
            }
        } else {
            if ($existing !== null) {
                $existing->delete();
            }
        }

        // Sync legacy flat `bookmarks` table
        $isInAnyFolder = BookmarkFolderItem::query()
            ->whereHas('folder', fn ($q) => $q->where('user_id', $userId))
            ->where('question_id', $questionId)
            ->exists();

        $target = [
            'user_id' => $userId,
            'bookmarkable_type' => Bookmark::TYPE_QUESTION,
            'bookmarkable_id' => $questionId,
        ];

        if ($isInAnyFolder) {
            Bookmark::query()->firstOrCreate($target);
        } else {
            Bookmark::query()->where($target)->delete();
        }

        Auditor::record(
            AuditAction::LearningBookmarkChanged,
            $user,
            $folder,
            metadata: [
                'question_id' => $questionId,
                'in_folder' => $shouldBeInFolder,
                'bookmarked' => $isInAnyFolder,
            ],
        );

        return $this->getFolders->handle($user, $questionId);
    }
}
