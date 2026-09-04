<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Modules\AiAssistant\Enums\TutorPreset;
use Modules\AiAssistant\Models\AiThread;
use Modules\AiAssistant\Services\TutorPromptFactory;
use Modules\AiAssistant\Services\TutorResponseCache;
use Tests\TestCase;

final class TutorResponseCacheTest extends TestCase
{
    public function test_put_and_get_round_trip(): void
    {
        Cache::flush();

        $cache = new TutorResponseCache(new TutorPromptFactory);
        $thread = new AiThread([
            'context_type' => 'question',
            'context_id' => '42',
            'preset' => TutorPreset::ExplainMistake->value,
        ]);

        $pack = [
            'question_id' => '42',
            'answered' => true,
            'is_correct_attempt' => false,
            'stem' => 'Stem',
            'options' => [['label' => 'A', 'content' => 'x']],
            'official_explanation' => 'Why',
        ];

        $this->assertNull($cache->get($thread, $pack));

        $cache->put($thread, $pack, 'Cached answer', []);

        $hit = $cache->get($thread, $pack);
        $this->assertNotNull($hit);
        $this->assertSame('Cached answer', $hit['content']);
    }

    public function test_fingerprint_changes_when_stem_edits(): void
    {
        $cache = new TutorResponseCache(new TutorPromptFactory);

        $a = $cache->fingerprint(['stem' => 'One', 'options' => []]);
        $b = $cache->fingerprint(['stem' => 'Two', 'options' => []]);

        $this->assertNotSame($a, $b);
    }

    public function test_is_cacheable_only_for_matching_auto_prompt(): void
    {
        $prompts = new TutorPromptFactory;
        $cache = new TutorResponseCache($prompts);

        $thread = new AiThread([
            'context_type' => 'question',
            'context_id' => '42',
            'preset' => TutorPreset::AnalyzeWithoutSpoiler->value,
        ]);
        $pack = ['answered' => false];

        $auto = $prompts->autoPromptContent(TutorPreset::AnalyzeWithoutSpoiler, $pack);
        $this->assertTrue($cache->isCacheableAutoStart($thread, $auto, $pack));
        $this->assertFalse($cache->isCacheableAutoStart($thread, 'Câu hỏi tùy ý của học viên', $pack));
    }
}
