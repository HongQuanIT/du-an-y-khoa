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

final class CreateBookmarkFolderAction
{
    use AsAction;

    public function __construct(
        private readonly GetBookmarkFoldersAction $getFolders,
    ) {}

    /**
     * @return array{folders: list<array{id: int, name: string, in_folder: bool}>, bookmarked: bool}
     */
    public function handle(User $user, string $name, ?string $questionId = null): array
    {
        $userId = (int) $user->getKey();
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            $trimmedName = 'Thư mục mới';
        }

        $folder = BookmarkFolder::query()->firstOrCreate([
            'user_id' => $userId,
            'name' => $trimmedName,
        ]);

        if ($questionId !== null && $questionId !== '') {
            BookmarkFolderItem::query()->firstOrCreate([
                'folder_id' => $folder->id,
                'question_id' => $questionId,
            ]);

            Bookmark::query()->firstOrCreate([
                'user_id' => $userId,
                'bookmarkable_type' => Bookmark::TYPE_QUESTION,
                'bookmarkable_id' => $questionId,
            ]);
        }

        Auditor::record(
            AuditAction::LearningBookmarkFolderCreated,
            $user,
            $folder,
            metadata: [
                'question_id' => $questionId,
                'created' => $folder->wasRecentlyCreated,
            ],
        );

        return $this->getFolders->handle($user, $questionId ?? '');
    }
}
