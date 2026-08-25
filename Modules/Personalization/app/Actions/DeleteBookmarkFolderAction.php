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

final class DeleteBookmarkFolderAction
{
    use AsAction;

    public function handle(User $user, BookmarkFolder $folder): void
    {
        $userId = (int) $user->getKey();
        assert((int) $folder->user_id === $userId);

        $questionIds = BookmarkFolderItem::query()
            ->where('folder_id', $folder->id)
            ->pluck('question_id')
            ->all();

        Auditor::record(
            AuditAction::LearningBookmarkFolderDeleted,
            $user,
            $folder,
            before: ['items_count' => count($questionIds)],
        );

        BookmarkFolderItem::query()->where('folder_id', $folder->id)->delete();
        $folder->delete();

        foreach ($questionIds as $qId) {
            $stillInAnyFolder = BookmarkFolderItem::query()
                ->whereHas('folder', fn ($q) => $q->where('user_id', $userId))
                ->where('question_id', $qId)
                ->exists();

            if (! $stillInAnyFolder) {
                Bookmark::query()
                    ->where('user_id', $userId)
                    ->where('bookmarkable_type', Bookmark::TYPE_QUESTION)
                    ->where('bookmarkable_id', $qId)
                    ->delete();
            }
        }
    }
}
