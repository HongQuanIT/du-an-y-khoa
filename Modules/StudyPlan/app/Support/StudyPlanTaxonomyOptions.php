<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Support;

use Illuminate\Support\Collection;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\MedicalTaxonomy;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;

final class StudyPlanTaxonomyOptions
{
    /** @var array<string, array{label: string, types: list<string>}> */
    private const ADDITIONAL_GROUPS = [
        'diseases' => ['label' => 'Bệnh và tình trạng', 'types' => ['disease', 'condition']],
        'presentations' => ['label' => 'Triệu chứng và dấu hiệu', 'types' => ['symptom', 'sign']],
        'diagnostics' => ['label' => 'Phát hiện và cận lâm sàng', 'types' => ['clinical_finding', 'lab_finding', 'imaging_finding']],
        'knowledge' => ['label' => 'Kiến thức và can thiệp', 'types' => ['concept', 'procedure', 'drug']],
    ];

    /** @return Collection<int, MedicalTaxonomyNode> */
    public function systems(): Collection
    {
        return $this->nodes(['system']);
    }

    /** @return Collection<int, MedicalTaxonomyNode> */
    public function specialties(): Collection
    {
        return $this->nodes(['specialty']);
    }

    /** @return list<array{key: string, label: string, options: list<array{id: int, name: string}>}> */
    public function additionalGroups(): array
    {
        return collect(self::ADDITIONAL_GROUPS)
            ->map(function (array $group, string $key): array {
                $options = $this->nodes($group['types'])
                    ->map(fn (MedicalTaxonomyNode $node): array => [
                        'id' => (int) $node->getKey(),
                        'name' => $node->name,
                    ])
                    ->values()
                    ->all();

                return ['key' => $key, 'label' => $group['label'], 'options' => $options];
            })
            ->filter(fn (array $group): bool => $group['options'] !== [])
            ->values()
            ->all();
    }

    /** @param list<string> $types */
    private function nodes(array $types): Collection
    {
        $canonicalId = MedicalTaxonomy::canonical()?->getKey();

        return MedicalTaxonomyNode::query()
            ->when($canonicalId !== null, fn ($query) => $query->where('medical_taxonomy_id', $canonicalId))
            ->where('status', TaxonomyStatus::Active)
            ->whereIn('node_type', $types)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
