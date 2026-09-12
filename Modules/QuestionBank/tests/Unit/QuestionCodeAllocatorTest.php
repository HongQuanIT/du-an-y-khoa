<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionCodeAllocator;
use Tests\TestCase;

final class QuestionCodeAllocatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocates_sequential_q_codes(): void
    {
        $allocator = app(QuestionCodeAllocator::class);

        $this->assertSame('Q00001', $allocator->allocate());
        $this->assertSame('Q00002', $allocator->allocate());
        $this->assertSame('Q00003', $allocator->allocate());
    }

    public function test_formats_with_five_digit_padding(): void
    {
        $this->assertSame('Q00042', app(QuestionCodeAllocator::class)->format(42));
    }

    public function test_question_auto_assigns_code_on_create(): void
    {
        $first = Question::factory()->create();
        $second = Question::factory()->create();

        $this->assertSame('Q00001', $first->code);
        $this->assertSame('Q00002', $second->code);
    }

    public function test_question_code_is_immutable_after_create(): void
    {
        $question = Question::factory()->create();
        $original = $question->code;

        $question->code = 'Q99999';
        $question->save();

        $this->assertSame($original, $question->fresh()->code);
    }

    public function test_explicit_legacy_code_is_preserved(): void
    {
        $question = Question::factory()->create(['code' => 'CARDIO-STEMI-001']);
        $next = Question::factory()->create();

        $this->assertSame('CARDIO-STEMI-001', $question->code);
        $this->assertSame('Q00001', $next->code);
    }

    public function test_explicit_q_code_advances_sequence(): void
    {
        Question::factory()->create(['code' => 'Q00010']);
        $next = Question::factory()->create();

        $this->assertSame('Q00011', $next->code);
    }
}
