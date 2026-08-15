<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Support\Cms\CmsPageContentResolver;
use Modules\Admin\Support\Cms\CmsPageDefaults;
use Modules\Admin\Support\Cms\CmsPageSeo;
use Modules\Admin\Support\Enums\CmsPageKey;
use Modules\Admin\Support\Enums\CmsPageStatus;

class CmsPage extends Model
{
    protected $fillable = [
        'key',
        'slug',
        'title',
        'content',
        'seo',
        'status',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => CmsPageKey::class,
            'content' => 'array',
            'seo' => 'array',
            'status' => CmsPageStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function getRouteKey(): mixed
    {
        return $this->key?->value ?? parent::getRouteKey();
    }

    public function isPublished(): bool
    {
        return $this->status === CmsPageStatus::Published;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedContent(): array
    {
        $key = $this->key;

        if ($key === null) {
            return [];
        }

        return CmsPageContentResolver::resolve($this, $key);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedSeo(): array
    {
        $key = $this->key;

        if ($key === null) {
            return CmsPageSeo::defaults(CmsPageKey::About);
        }

        return CmsPageSeo::merged($this, $key);
    }

    public function metaTitle(): string
    {
        $seo = $this->resolvedSeo();

        return trim((string) ($seo['meta_title'] ?? '')) ?: $this->title;
    }

    public function metaDescription(): string
    {
        $seo = $this->resolvedSeo();

        return trim((string) ($seo['meta_description'] ?? ''))
            ?: ($this->key?->defaultSeoDescription() ?? '');
    }

    public function publicUrl(): ?string
    {
        $key = $this->key;

        if ($key === null) {
            return null;
        }

        // Landing blocks stay reachable even when draft (defaults fallback).
        if (! $this->isPublished() && ! $key->alwaysPublic()) {
            return null;
        }

        return route($key->routeName());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', CmsPageStatus::Published->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('key');
    }

    public static function findPublished(CmsPageKey $key): ?self
    {
        return self::query()
            ->where('key', $key->value)
            ->published()
            ->first();
    }

    public static function syncCatalog(): void
    {
        foreach (CmsPageKey::cases() as $key) {
            self::query()->firstOrCreate(
                ['key' => $key->value],
                [
                    'slug' => $key === CmsPageKey::Home ? 'home' : ltrim($key->slug(), '/'),
                    'title' => $key->defaultTitle(),
                    'content' => CmsPageDefaults::for($key),
                    'status' => CmsPageStatus::Draft,
                    'seo' => CmsPageSeo::defaults($key),
                ],
            );
        }
    }
}
