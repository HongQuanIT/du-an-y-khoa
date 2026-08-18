<?php

declare(strict_types=1);

namespace Modules\Media\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Admin\Support\Auditor;
use Modules\Media\Models\Media;
use Modules\Media\Support\Enums\MediaStatus;
use Modules\Media\Support\Enums\MediaType;
use Modules\Media\Support\ExternalUrlGuard;
use RuntimeException;

final class RegisterExternalMediaAction
{
    use AsAction;

    public function handle(User $actor, string $url, ?string $alt = null, bool $import = false): Media
    {
        $url = ExternalUrlGuard::assertHttpUrl($url);

        if ($import) {
            return $this->importToLocal($actor, $url, $alt);
        }

        $existing = Media::query()
            ->where('disk', Media::DISK_EXTERNAL)
            ->where('path', $url)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $type = $this->guessType($url);
        $name = $this->filenameFromUrl($url);

        $media = Media::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'disk' => Media::DISK_EXTERNAL,
            'path' => $url,
            'variants' => [
                'original' => ['path' => $url],
            ],
            'original_name' => $name,
            'mime' => null,
            'size_bytes' => 0,
            'alt' => $this->normalizeAlt($alt) ?? pathinfo($name, PATHINFO_FILENAME),
            'credit' => parse_url($url, PHP_URL_HOST) ?: null,
            'is_premium' => false,
            'status' => MediaStatus::Ready,
            'uploaded_by' => $actor->id,
        ]);

        Auditor::record('media.external', $actor, $media, null, [
            'url' => $url,
            'imported' => false,
        ]);

        return $media;
    }

    private function importToLocal(User $actor, string $url, ?string $alt): Media
    {
        ExternalUrlGuard::assertSafeToFetch($url);

        $maxBytes = (int) config('media.image_max_kb', 10240) * 1024;

        try {
            $response = Http::timeout(12)
                ->withHeaders(['User-Agent' => 'MedLearn-Media/1.0'])
                ->withOptions(['allow_redirects' => ['max' => 3, 'strict' => true]])
                ->get($url);
        } catch (\Throwable $e) {
            throw new RuntimeException('Không tải được URL: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('URL trả về HTTP '.$response->status().'.');
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > $maxBytes) {
            throw new RuntimeException('File quá lớn hoặc rỗng.');
        }

        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        if (! isset($allowed[$mime])) {
            throw new RuntimeException('URL không phải ảnh (jpg, png, gif, webp).');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'media-import-');
        if ($tmp === false) {
            throw new RuntimeException('Không tạo được file tạm.');
        }

        file_put_contents($tmp, $body);

        $name = $this->filenameFromUrl($url);
        if (! str_contains($name, '.')) {
            $name .= '.'.$allowed[$mime];
        }

        $file = new UploadedFile($tmp, $name, $mime, null, true);

        try {
            $media = UploadMediaAction::run($actor, $file, $alt);
        } finally {
            @unlink($tmp);
        }

        $variants = $media->variants ?? [];
        $variants['source_url'] = ['path' => $url];
        $media->forceFill([
            'variants' => $variants,
            'credit' => $media->credit ?: (parse_url($url, PHP_URL_HOST) ?: null),
        ])->save();

        return $media->refresh();
    }

    private function guessType(string $url): MediaType
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        return in_array($ext, ['mp4', 'webm', 'mov'], true)
            ? MediaType::Video
            : MediaType::Image;
    }

    private function filenameFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $base = basename($path);

        return $base !== '' && $base !== '/' ? $base : 'cdn-image';
    }

    private function normalizeAlt(?string $alt): ?string
    {
        if ($alt === null) {
            return null;
        }

        $text = trim(strip_tags($alt));

        return $text === '' ? null : $text;
    }
}
