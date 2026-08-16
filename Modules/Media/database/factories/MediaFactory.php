<?php

declare(strict_types=1);

namespace Modules\Media\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Media\Models\Media;
use Modules\Media\Support\Enums\MediaStatus;
use Modules\Media\Support\Enums\MediaType;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'type' => MediaType::Image,
            'disk' => 'public',
            'path' => 'media/'.$uuid.'/original.jpg',
            'variants' => [
                'original' => ['path' => 'media/'.$uuid.'/original.jpg', 'width' => 800, 'height' => 600],
                'thumb' => ['path' => 'media/'.$uuid.'/thumb.webp', 'width' => 400, 'height' => 300],
                'lg' => ['path' => 'media/'.$uuid.'/lg.webp', 'width' => 800, 'height' => 600],
            ],
            'original_name' => 'hero.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 12000,
            'alt' => 'Ảnh minh họa',
            'caption' => null,
            'credit' => null,
            'is_premium' => false,
            'status' => MediaStatus::Ready,
        ];
    }

    public function video(): self
    {
        return $this->state(function (array $attributes): array {
            $uuid = (string) ($attributes['uuid'] ?? Str::uuid());

            return [
                'type' => MediaType::Video,
                'path' => 'media/'.$uuid.'/original.mp4',
                'variants' => [
                    'original' => ['path' => 'media/'.$uuid.'/original.mp4'],
                ],
                'original_name' => 'clip.mp4',
                'mime' => 'video/mp4',
            ];
        });
    }

    public function processing(): self
    {
        return $this->state(fn (): array => ['status' => MediaStatus::Processing]);
    }

    public function external(?string $url = null): self
    {
        $url ??= 'https://cdn.example.com/hero.jpg';

        return $this->state(fn (): array => [
            'disk' => Media::DISK_EXTERNAL,
            'path' => $url,
            'variants' => [
                'original' => ['path' => $url],
            ],
            'original_name' => basename((string) parse_url($url, PHP_URL_PATH)) ?: 'cdn-image',
            'mime' => null,
            'size_bytes' => 0,
            'credit' => parse_url($url, PHP_URL_HOST) ?: 'cdn.example.com',
        ]);
    }
}
