<?php

declare(strict_types=1);

namespace Modules\Media\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Admin\Support\Auditor;
use Modules\Media\Models\Media;
use Modules\Media\Support\Enums\MediaStatus;
use Modules\Media\Support\Enums\MediaType;

final class UploadMediaAction
{
    use AsAction;

    public function handle(User $actor, UploadedFile $file, ?string $alt = null): Media
    {
        $type = $this->detectType($file);
        $disk = (string) config('media.disk_public', 'public');
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $directory = 'media/'.$uuid;
        $path = $file->storeAs($directory, 'original.'.$extension, $disk);

        if ($path === false) {
            throw new \RuntimeException('Không lưu được tệp.');
        }

        $isImage = $type === MediaType::Image;

        $media = Media::query()->create([
            'uuid' => $uuid,
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
            'variants' => [
                'original' => ['path' => $path],
            ],
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: (Storage::disk($disk)->size($path) ?: 0),
            'alt' => $this->normalizeAlt($alt) ?? $this->fallbackAlt($file),
            'is_premium' => false,
            'status' => $isImage ? MediaStatus::Processing : MediaStatus::Ready,
            'uploaded_by' => $actor->id,
        ]);

        if ($isImage) {
            ProcessImageVariantsAction::run($media);
        }

        Auditor::record('media.upload', $actor, $media->fresh(), null, [
            'type' => $type->value,
            'original_name' => $media->original_name,
            'status' => $media->fresh()?->status?->value,
        ]);

        return $media->refresh();
    }

    private function detectType(UploadedFile $file): MediaType
    {
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());

        if (str_starts_with($mime, 'image/')) {
            return MediaType::Image;
        }

        if (str_starts_with($mime, 'video/')) {
            return MediaType::Video;
        }

        if (str_starts_with($mime, 'audio/')) {
            return MediaType::Audio;
        }

        return MediaType::Document;
    }

    private function normalizeAlt(?string $alt): ?string
    {
        if ($alt === null) {
            return null;
        }

        $text = trim(strip_tags($alt));

        return $text === '' ? null : $text;
    }

    private function fallbackAlt(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        return $name !== '' ? $name : 'Media';
    }
}
