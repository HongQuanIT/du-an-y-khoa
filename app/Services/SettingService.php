<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class SettingService
{
    private const CACHE_KEY = 'settings.all';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDay(), fn () => $this->loadFromDatabase());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    public function set(string $key, mixed $value, string $type = 'string'): Setting
    {
        [$group, $settingKey] = $this->splitKey($key);

        $setting = Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $settingKey],
            [
                'value' => $this->serializeValue($value, $type),
                'type' => $type,
            ],
        );

        $this->forgetCache();

        return $setting;
    }

    /**
     * @param  array<string, array{value: mixed, type?: string}|mixed>  $settings
     */
    public function updateMany(array $settings): void
    {
        foreach ($settings as $key => $payload) {
            if (is_array($payload) && array_key_exists('value', $payload)) {
                $this->set($key, $payload['value'], (string) ($payload['type'] ?? 'string'));

                continue;
            }

            $this->set($key, $payload);
        }
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadFromDatabase(): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        return Setting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->reduce(function (array $settings, Setting $setting): array {
                $settings[$setting->group][$setting->key] = $this->castValue($setting->value, $setting->type);

                return $settings;
            }, []);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitKey(string $key): array
    {
        $parts = explode('.', $key, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException('Setting key must use the group.key format.');
        }

        return $parts;
    }

    private function serializeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }

    private function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true, 512, JSON_THROW_ON_ERROR),
            default => $value,
        };
    }
}
