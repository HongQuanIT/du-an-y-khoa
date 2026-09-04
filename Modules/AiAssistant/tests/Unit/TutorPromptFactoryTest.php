<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Tests\Unit;

use Modules\AiAssistant\Enums\TutorPreset;
use Modules\AiAssistant\Services\TutorPromptFactory;
use Tests\TestCase;

final class TutorPromptFactoryTest extends TestCase
{
    public function test_context_block_is_minified_without_pretty_print(): void
    {
        $factory = new TutorPromptFactory;
        $pack = [
            'question_id' => 'q1',
            'stem' => 'Stem A',
            'answered' => true,
        ];

        $block = $factory->contextBlock($pack);

        $this->assertStringStartsWith('CONTEXT:', $block);
        $this->assertStringNotContainsString("\n  ", $block);
        $this->assertStringContainsString('"question_id":"q1"', $block);
    }

    public function test_system_messages_split_static_and_context(): void
    {
        $factory = new TutorPromptFactory;
        $pack = ['question_id' => 'q1', 'answered' => false];

        $full = $factory->systemMessages($pack, TutorPreset::AnalyzeWithoutSpoiler, includeFullContext: true);
        $this->assertCount(2, $full);
        $this->assertStringContainsString('AI Tutor', $full[0]);
        $this->assertStringStartsWith('CONTEXT:', $full[1]);
        $this->assertStringNotContainsString('CONTEXT:', $full[0]);

        $ref = $factory->systemMessages($pack, TutorPreset::AnalyzeWithoutSpoiler, includeFullContext: false);
        $this->assertCount(2, $ref);
        $this->assertStringStartsWith('CONTEXT_REF:', $ref[1]);
        $this->assertStringNotContainsString('CONTEXT:{', $ref[1]);
    }
}
