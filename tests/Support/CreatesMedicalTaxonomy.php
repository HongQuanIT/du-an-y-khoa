<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Str;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\MedicalTaxonomy;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;

trait CreatesMedicalTaxonomy
{
    protected function makeMedicalTaxonomy(string $code = 'test-taxonomy'): MedicalTaxonomy
    {
        return MedicalTaxonomy::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => 'Test taxonomy',
                'description' => null,
                'status' => TaxonomyStatus::Active,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeMedicalNode(array $overrides = []): MedicalTaxonomyNode
    {
        $taxonomy = $overrides['taxonomy'] ?? $this->makeMedicalTaxonomy();
        unset($overrides['taxonomy']);

        $name = (string) ($overrides['name'] ?? 'Tim mạch');
        $slug = (string) ($overrides['slug'] ?? Str::slug($name).'-'.Str::random(4));

        return MedicalTaxonomyNode::query()->create(array_merge([
            'medical_taxonomy_id' => $taxonomy->id,
            'parent_id' => null,
            'name' => $name,
            'slug' => $slug,
            'code' => $overrides['code'] ?? null,
            'node_type' => $overrides['node_type'] ?? 'system',
            'description' => null,
            'sort_order' => $overrides['sort_order'] ?? 0,
            'status' => TaxonomyStatus::Active,
        ], $overrides));
    }
}
