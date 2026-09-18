<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\BlueprintSection;
use Modules\QuestionBank\Models\CoreClinicalTopic;
use Modules\QuestionBank\Support\BlueprintExamAllocator;
use Tests\TestCase;

final class BlueprintExamAllocatorTest extends TestCase
{
    use RefreshDatabase;
    public function test_allocates_exact_total_from_section_and_topic_weights(): void
    {
        $blueprint = Blueprint::query()->create([
            'name' => 'Đề TNLS',
            'slug' => 'de-tnls-allocator',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
            'total_questions' => 100,
        ]);

        $internal = BlueprintSection::query()->create([
            'blueprint_id' => $blueprint->id,
            'name' => 'Nội',
            'slug' => 'noi-alloc',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
            'weight_min' => 40,
            'weight_max' => 40,
        ]);

        $surgery = BlueprintSection::query()->create([
            'blueprint_id' => $blueprint->id,
            'name' => 'Ngoại',
            'slug' => 'ngoai-alloc',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 2,
            'weight_min' => 60,
            'weight_max' => 60,
        ]);

        CoreClinicalTopic::query()->create([
            'blueprint_section_id' => $internal->id,
            'name' => 'Tim mạch',
            'slug' => 'tim-mach-alloc',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
            'weight' => 50,
        ]);
        CoreClinicalTopic::query()->create([
            'blueprint_section_id' => $internal->id,
            'name' => 'Hô hấp',
            'slug' => 'ho-hap-alloc',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 2,
            'weight' => 50,
        ]);
        CoreClinicalTopic::query()->create([
            'blueprint_section_id' => $surgery->id,
            'name' => 'Tiêu hóa',
            'slug' => 'tieu-hoa-alloc',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
            'weight' => 100,
        ]);

        $result = app(BlueprintExamAllocator::class)->allocate($blueprint->fresh());

        $this->assertTrue($result['ready']);
        $this->assertSame(100, $result['total_questions']);
        $this->assertSame(150, $result['suggested_duration_minutes']);
        $this->assertSame(40, $result['sections'][0]['question_count']);
        $this->assertSame(60, $result['sections'][1]['question_count']);
        $this->assertSame(20, $result['sections'][0]['topics'][0]['question_count']);
        $this->assertSame(20, $result['sections'][0]['topics'][1]['question_count']);
        $this->assertSame(60, $result['sections'][1]['topics'][0]['question_count']);
    }

    public function test_returns_not_ready_when_total_questions_missing(): void
    {
        $blueprint = Blueprint::query()->create([
            'name' => 'Ma trận trống',
            'slug' => 'ma-tran-trong-alloc',
            'status' => TaxonomyStatus::Active,
            'sort_order' => 1,
            'total_questions' => null,
        ]);

        $result = app(BlueprintExamAllocator::class)->allocate($blueprint);

        $this->assertFalse($result['ready']);
        $this->assertStringContainsString('tổng số câu', (string) $result['reason']);
    }
}
