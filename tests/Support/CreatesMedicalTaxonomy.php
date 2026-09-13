<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Str;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\OrganSystem;
use Modules\QuestionBank\Models\Subject;

/**
 * Test helper for the curriculum taxonomy:
 * Hệ cơ quan (OrganSystem) and Môn học (Subject) are independent;
 * Bài học (Lesson) may attach zero or more of each.
 */
trait CreatesMedicalTaxonomy
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeOrganSystem(array $overrides = []): OrganSystem
    {
        unset($overrides['code']);

        $name = (string) ($overrides['name'] ?? 'Hệ tim mạch');
        $slug = (string) ($overrides['slug'] ?? Str::slug($name).'-'.Str::random(4));

        return OrganSystem::query()->create(array_merge([
            'name' => $name,
            'slug' => $slug,
            'description' => null,
            'sort_order' => $overrides['sort_order'] ?? 0,
            'status' => TaxonomyStatus::Active,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeSubject(array $overrides = []): Subject
    {
        unset($overrides['organSystems'], $overrides['code']);

        $name = (string) ($overrides['name'] ?? 'Nội tim mạch');
        $slug = (string) ($overrides['slug'] ?? Str::slug($name).'-'.Str::random(4));

        return Subject::query()->create(array_merge([
            'name' => $name,
            'slug' => $slug,
            'description' => null,
            'sort_order' => $overrides['sort_order'] ?? 0,
            'status' => TaxonomyStatus::Active,
        ], $overrides));
    }

    /**
     * Create a lesson (bài học). Optionally attach subjects and organ systems.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function makeLesson(array $overrides = []): Lesson
    {
        $subjects = $overrides['subjects'] ?? null;
        $organSystems = $overrides['organSystems'] ?? null;
        unset($overrides['subjects'], $overrides['organSystems'], $overrides['code']);

        $name = (string) ($overrides['name'] ?? 'Tăng huyết áp');
        $slug = (string) ($overrides['slug'] ?? Str::slug($name).'-'.Str::random(4));

        $lesson = Lesson::query()->create(array_merge([
            'name' => $name,
            'slug' => $slug,
            'description' => null,
            'sort_order' => $overrides['sort_order'] ?? 0,
            'status' => TaxonomyStatus::Active,
        ], $overrides));

        if ($subjects !== null) {
            $lesson->subjects()->sync(
                collect($subjects)->map(fn ($s) => $s instanceof Subject ? $s->id : $s)->all()
            );
        }

        if ($organSystems !== null) {
            $lesson->organSystems()->sync(
                collect($organSystems)->map(fn ($s) => $s instanceof OrganSystem ? $s->id : $s)->all()
            );
        }

        return $lesson;
    }

    /**
     * Backward-compatible alias: previously created a MedicalTaxonomyNode,
     * now creates a Lesson (the standard unit of knowledge).
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function makeMedicalNode(array $overrides = []): Lesson
    {
        unset($overrides['taxonomy'], $overrides['node_type'], $overrides['parent_id'], $overrides['medical_taxonomy_id']);

        return $this->makeLesson($overrides);
    }
}
