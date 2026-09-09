<?php

declare(strict_types=1);

namespace Modules\StudyPlan\Services;

use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Services\SessionQuestionSelector;
use Modules\StudyPlan\Models\StudyPlan;

final class StudyPlanQuestionPool
{
    public function __construct(private readonly SessionQuestionSelector $selector) {}

    /** @return list<string> */
    public function questionIds(StudyPlan $plan): array
    {
        $filters = $plan->scopeFilters();
        $user = $plan->user()->firstOrFail();
        $data = $this->sessionData($filters, 1);
        $count = $this->selector->countForSession($user, $data);

        if ($count === 0) {
            return [];
        }

        return array_values(array_unique($this->selector->forSession(
            $user,
            $this->sessionData($filters, $count),
        )));
    }

    /** @param array<string, mixed> $filters */
    private function sessionData(array $filters, int $count): CreateSessionData
    {
        return new CreateSessionData(
            mode: SessionMode::Study,
            source: SessionSource::Custom,
            count: max(1, $count),
            blueprintId: isset($filters['blueprint_id']) ? (int) $filters['blueprint_id'] : null,
            blueprintSectionId: isset($filters['blueprint_section_id']) ? (int) $filters['blueprint_section_id'] : null,
            coreClinicalTopicIds: array_map('intval', $filters['core_clinical_topic_ids'] ?? []),
            medicalTaxonomyNodeIds: array_map('intval', $filters['medical_taxonomy_node_ids'] ?? []),
            tagIds: array_map('intval', $filters['tag_ids'] ?? []),
            difficulties: array_values($filters['difficulties'] ?? []),
            questionStatuses: array_values($filters['question_statuses'] ?? []),
            questionStatusMode: (string) ($filters['question_status_mode'] ?? 'latest'),
            savedOnly: (bool) ($filters['saved_only'] ?? false),
            examKey: ($filters['exam_tags'][0] ?? null) ?: null,
            articles: array_values($filters['articles'] ?? []),
            symptoms: array_values($filters['symptoms'] ?? []),
        );
    }
}
