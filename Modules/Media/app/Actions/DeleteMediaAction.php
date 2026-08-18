<?php

declare(strict_types=1);

namespace Modules\Media\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\Storage;
use Modules\Admin\Support\Auditor;
use Modules\Media\Models\Media;
use RuntimeException;

final class DeleteMediaAction
{
    use AsAction;

    public function handle(User $actor, Media $media): void
    {
        if ($media->usages()->exists()) {
            throw new RuntimeException('Không thể xóa media đang được sử dụng.');
        }

        $before = $media->only(['uuid', 'type', 'path', 'original_name']);

        $this->deleteFiles($media);
        $media->delete();

        Auditor::record('media.delete', $actor, $media, $before, null);
    }

    private function deleteFiles(Media $media): void
    {
        if ($media->isExternal()) {
            return;
        }
        $disk = Storage::disk($media->disk);
        $directory = dirname($media->path);

        if ($directory !== '.' && $directory !== '' && $disk->exists($directory)) {
            $disk->deleteDirectory($directory);

            return;
        }

        if ($disk->exists($media->path)) {
            $disk->delete($media->path);
        }
    }
}
