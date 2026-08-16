<?php

declare(strict_types=1);

namespace Modules\Media\Support;

use Illuminate\Support\Collection;
use Modules\Media\Models\Media;

final class HydrateMediaUrls
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function apply(array $payload): array
    {
        $ids = self::collectIds($payload);

        if ($ids === []) {
            return $payload;
        }

        $map = Media::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return self::walk($payload, $map);
    }

    /**
     * @param  array<mixed>  $payload
     * @return list<int>
     */
    public static function collectIds(array $payload): array
    {
        $ids = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $ids = array_merge($ids, self::collectIds($value));

                continue;
            }

            if (is_string($key) && str_ends_with($key, '_media_id') && $value !== null && $value !== '') {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, Media>  $map
     * @return array<string, mixed>
     */
    private static function walk(array $payload, $map): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::walk($value, $map);

                continue;
            }

            if (! is_string($key) || ! str_ends_with($key, '_media_id') || $value === null || $value === '') {
                continue;
            }

            $media = $map->get((int) $value);
            if (! $media instanceof Media) {
                continue;
            }

            $base = substr($key, 0, -9);
            $urlKey = array_key_exists($base.'_url', $payload) ? $base.'_url' : $base;
            $url = $media->publicUrl('lg') ?? $media->publicUrl();

            if ($url !== null && array_key_exists($urlKey, $payload)) {
                $payload[$urlKey] = $url;
            }

            $altKey = $base === 'image' ? 'image_alt' : $base.'_alt';
            if (array_key_exists($altKey, $payload) && (trim((string) $payload[$altKey]) === '') && $media->alt) {
                $payload[$altKey] = $media->alt;
            }
        }

        return $payload;
    }
}
