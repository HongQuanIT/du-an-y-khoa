<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use Illuminate\Support\Facades\Cache;
use Modules\AiAssistant\Enums\TutorPreset;
use Modules\AiAssistant\Models\AiThread;
use Modules\AiAssistant\Support\AiTutorSettings;

/**
 * Shared cache of identical auto-start tutor replies (same question pack + preset).
 * Skips OpenAI when hit; product quota is still consumed by the caller.
 */
final class TutorResponseCache
{
    public function __construct(
        private readonly TutorPromptFactory $prompts,
    ) {}

    public function enabled(): bool
    {
        return AiTutorSettings::responseCacheEnabled();
    }

    /**
     * Only cache the first auto-start bubble (content equals server auto-prompt).
     *
     * @param  array<string, mixed>  $pack
     */
    public function isCacheableAutoStart(AiThread $thread, string $userContent, array $pack): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if ($thread->context_type === null || $thread->context_id === null || $thread->preset === null) {
            return false;
        }

        $preset = TutorPreset::tryFrom((string) $thread->preset);
        if ($preset === null) {
            return false;
        }

        $expected = $this->prompts->autoPromptContent($preset, $pack);

        return hash_equals($expected, trim($userContent));
    }

    /**
     * @param  array<string, mixed>  $pack
     * @return array{content: string, citations: array<int, array<string, mixed>>}|null
     */
    public function get(AiThread $thread, array $pack, ?string $selection = null): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $cached = Cache::get($this->key($thread, $pack, $selection));
        if (! is_array($cached) || ! isset($cached['content']) || ! is_string($cached['content'])) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $citations */
        $citations = is_array($cached['citations'] ?? null) ? $cached['citations'] : [];

        return [
            'content' => $cached['content'],
            'citations' => $citations,
        ];
    }

    /**
     * @param  array<string, mixed>  $pack
     * @param  array<int, array<string, mixed>>  $citations
     */
    public function put(AiThread $thread, array $pack, string $content, array $citations = [], ?string $selection = null): void
    {
        if (! $this->enabled() || trim($content) === '') {
            return;
        }

        Cache::put(
            $this->key($thread, $pack, $selection),
            ['content' => $content, 'citations' => $citations],
            AiTutorSettings::responseCacheTtlSeconds(),
        );
    }

    /**
     * @param  array<string, mixed>  $pack
     */
    public function key(AiThread $thread, array $pack, ?string $selection = null): string
    {
        $payload = [
            'context_type' => $thread->context_type,
            'context_id' => $thread->context_id,
            'preset' => $thread->preset,
            'answered' => (bool) ($pack['answered'] ?? false),
            'is_correct' => $pack['is_correct_attempt'] ?? null,
            'fingerprint' => $this->fingerprint($pack),
            'selection' => $selection !== null ? mb_substr($selection, 0, 500) : null,
        ];

        return 'ai:tutor:out:'.sha1(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** Stable hash of the material that shapes the answer (invalidates on content edit). */
    public function fingerprint(array $pack): string
    {
        $slice = [
            'stem' => $pack['stem'] ?? null,
            'options' => $pack['options'] ?? null,
            'official_explanation' => $pack['official_explanation'] ?? null,
            'key_info' => $pack['key_info'] ?? null,
            'attending_tip' => $pack['attending_tip'] ?? null,
            'correct_labels' => $pack['correct_labels'] ?? null,
        ];

        return sha1(json_encode($slice, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
