<?php

declare(strict_types=1);

namespace Modules\Media\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Models\Media;
use Modules\Media\Models\MediaJob;
use Modules\Media\Support\Enums\MediaJobStatus;
use Modules\Media\Support\Enums\MediaStatus;

final class ProcessImageVariantsAction
{
    use AsAction;

    /**
     * @var array<string, int>
     */
    private array $sizes;

    public function __construct()
    {
        $this->sizes = config('media.variants', [
            'thumb' => 400,
            'lg' => 1920,
        ]);
    }

    public function handle(Media $media): Media
    {
        $job = MediaJob::query()->create([
            'media_id' => $media->id,
            'type' => 'image.variants',
            'status' => MediaJobStatus::Running,
        ]);

        try {
            if (! function_exists('imagecreatefromstring')) {
                $media->forceFill(['status' => MediaStatus::Ready])->save();
                $job->update(['status' => MediaJobStatus::Completed]);

                return $media->refresh();
            }

            $disk = Storage::disk($media->disk);
            $absolute = $disk->path($media->path);

            if (! is_file($absolute)) {
                throw new \RuntimeException('Không tìm thấy file gốc.');
            }

            $binary = file_get_contents($absolute);
            if ($binary === false) {
                throw new \RuntimeException('Không đọc được file gốc.');
            }

            $source = @imagecreatefromstring($binary);
            if ($source === false) {
                throw new \RuntimeException('Định dạng ảnh không hỗ trợ.');
            }

            if (function_exists('imagepalettetotruecolor') && ! imageistruecolor($source)) {
                imagepalettetotruecolor($source);
            }

            if (function_exists('imagesavealpha')) {
                imagesavealpha($source, true);
            }

            $origW = imagesx($source);
            $origH = imagesy($source);
            $dir = dirname($media->path);

            $variants = [
                'original' => [
                    'path' => $media->path,
                    'width' => $origW,
                    'height' => $origH,
                ],
            ];

            foreach ($this->sizes as $name => $maxEdge) {
                $variants[$name] = $this->writeVariant($source, $disk, $dir, (string) $name, (int) $maxEdge, $origW, $origH);
            }

            imagedestroy($source);

            $media->forceFill([
                'variants' => $variants,
                'status' => MediaStatus::Ready,
            ])->save();

            $job->update(['status' => MediaJobStatus::Completed, 'error' => null]);
        } catch (\Throwable $e) {
            $job->update([
                'status' => MediaJobStatus::Failed,
                'error' => $e->getMessage(),
            ]);

            $media->forceFill(['status' => MediaStatus::Failed])->save();
        }

        return $media->refresh();
    }

    /**
     * @return array{path: string, width: int, height: int}
     */
    private function writeVariant(
        \GdImage $source,
        Filesystem $disk,
        string $dir,
        string $name,
        int $maxEdge,
        int $origW,
        int $origH,
    ): array {
        $scale = min(1, $maxEdge / max($origW, $origH, 1));
        $width = max(1, (int) round($origW * $scale));
        $height = max(1, (int) round($origH * $scale));

        $resized = imagescale($source, $width, $height);
        if ($resized === false) {
            throw new \RuntimeException('Không tạo được biến thể '.$name);
        }

        if (function_exists('imagesavealpha')) {
            imagesavealpha($resized, true);
        }

        $useWebp = function_exists('imagewebp');
        $filename = $name.($useWebp ? '.webp' : '.jpg');
        $path = $dir.'/'.$filename;
        $absolute = $disk->path($path);

        $directory = dirname($absolute);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Không tạo được thư mục biến thể.');
        }

        if ($useWebp) {
            imagewebp($resized, $absolute, 82);
        } else {
            imagejpeg($resized, $absolute, 85);
        }

        imagedestroy($resized);

        return [
            'path' => $path,
            'width' => $width,
            'height' => $height,
        ];
    }
}
