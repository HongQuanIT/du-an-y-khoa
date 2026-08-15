<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Support\Cms\MenuDefaults;
use Modules\Admin\Support\Enums\MenuKey;

class Menu extends Model
{
    protected $fillable = [
        'key',
        'name',
        'items',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => MenuKey::class,
            'items' => 'array',
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

    /**
     * @return array<string, mixed>
     */
    public function resolvedItems(): array
    {
        $key = $this->key;

        if ($key === null) {
            return [];
        }

        $defaults = MenuDefaults::for($key);
        $stored = is_array($this->items) ? $this->items : [];

        if ($stored === []) {
            return $defaults;
        }

        return match ($key) {
            MenuKey::Header => [
                'links' => is_array($stored['links'] ?? null) ? array_values($stored['links']) : $defaults['links'],
            ],
            MenuKey::Footer => [
                'brand_blurb' => array_key_exists('brand_blurb', $stored)
                    ? (string) $stored['brand_blurb']
                    : $defaults['brand_blurb'],
                'columns' => is_array($stored['columns'] ?? null) ? array_values($stored['columns']) : $defaults['columns'],
                'bottom_links' => is_array($stored['bottom_links'] ?? null)
                    ? array_values($stored['bottom_links'])
                    : $defaults['bottom_links'],
            ],
        };
    }

    public static function syncCatalog(): void
    {
        foreach (MenuKey::cases() as $key) {
            self::query()->firstOrCreate(
                ['key' => $key->value],
                [
                    'name' => $key->label(),
                    'items' => MenuDefaults::for($key),
                ],
            );
        }
    }

    public static function findByKey(MenuKey $key): ?self
    {
        return self::query()->where('key', $key->value)->first();
    }
}
