<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Support;

/**
 * Typed accessors for Admin `ai_tutor.*` system settings.
 *
 * DB settings override env/config defaults so ops can tune quota/cache without redeploy.
 */
final class AiTutorSettings
{
    public const GROUP = 'ai_tutor';

    public static function freeDailyLimit(): int
    {
        return max(0, (int) setting(
            'ai_tutor.free_daily_limit',
            (int) config('aiassistant.free_daily_limit', 10),
        ));
    }

    public static function premiumDailyLimit(): int
    {
        return max(1, (int) setting(
            'ai_tutor.premium_daily_limit',
            (int) config('aiassistant.premium_daily_limit', 100),
        ));
    }

    public static function historyMaxMessages(): int
    {
        return max(1, (int) setting(
            'ai_tutor.history_max_messages',
            (int) config('aiassistant.history_max_messages', 8),
        ));
    }

    public static function responseCacheEnabled(): bool
    {
        $fallback = (bool) config('aiassistant.response_cache', true);

        return (bool) setting('ai_tutor.response_cache', $fallback);
    }

    /** Cache TTL in days (Admin UI). */
    public static function responseCacheTtlDays(): int
    {
        $fallbackSeconds = (int) config('aiassistant.response_cache_ttl', 604800);
        $fallbackDays = max(1, (int) round($fallbackSeconds / 86400));

        return max(1, (int) setting('ai_tutor.response_cache_ttl_days', $fallbackDays));
    }

    public static function responseCacheTtlSeconds(): int
    {
        return self::responseCacheTtlDays() * 86400;
    }

    public static function tutorModel(): string
    {
        $model = (string) setting(
            'ai_tutor.tutor_model',
            (string) config('aiassistant.tutor_model', 'gpt-4.1-mini'),
        );

        return $model !== '' ? $model : 'gpt-4.1-mini';
    }

    public static function maxOutputTokens(): int
    {
        return max(100, min(4000, (int) setting(
            'ai_tutor.max_output_tokens',
            (int) config('aiassistant.max_output_tokens', 900),
        )));
    }

    /**
     * @return array<string, array{value: mixed, type: string}>
     */
    public static function defaultRows(): array
    {
        return [
            'ai_tutor.free_daily_limit' => [
                'value' => (int) config('aiassistant.free_daily_limit', 10),
                'type' => 'integer',
            ],
            'ai_tutor.premium_daily_limit' => [
                'value' => (int) config('aiassistant.premium_daily_limit', 100),
                'type' => 'integer',
            ],
            'ai_tutor.history_max_messages' => [
                'value' => (int) config('aiassistant.history_max_messages', 8),
                'type' => 'integer',
            ],
            'ai_tutor.response_cache' => [
                'value' => (bool) config('aiassistant.response_cache', true),
                'type' => 'boolean',
            ],
            'ai_tutor.response_cache_ttl_days' => [
                'value' => max(1, (int) round(((int) config('aiassistant.response_cache_ttl', 604800)) / 86400)),
                'type' => 'integer',
            ],
            'ai_tutor.tutor_model' => [
                'value' => (string) config('aiassistant.tutor_model', 'gpt-4.1-mini'),
                'type' => 'string',
            ],
            'ai_tutor.max_output_tokens' => [
                'value' => (int) config('aiassistant.max_output_tokens', 900),
                'type' => 'integer',
            ],
        ];
    }
}
