<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use App\Support\Enums\UserStatus;
use App\Support\Html\SafeHtml;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionHint;
use Modules\QuestionBank\Models\QuestionOption;

/**
 * Stable, allow-listed snapshots for entities covered by the admin audit trail.
 * Sensitive model attributes are deliberately never serialized here.
 */
final class AuditSnapshot
{
    /** @return array<string, mixed> */
    public static function user(User $user): array
    {
        $user->loadMissing('roles:id,name');

        return [
            'id' => (int) $user->getKey(),
            'status' => ($user->status ?? UserStatus::Active)->value,
            'roles' => $user->getRoleNames()->sort()->values()->all(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function question(Question $question): array
    {
        $question->loadMissing([
            'options' => fn ($query) => $query->orderBy('order'),
            'hints' => fn ($query) => $query->orderBy('sort_order'),
            'coreClinicalTopics:id',
            'medicalTaxonomyNodes:id',
            'tags:id',
        ]);

        return [
            'id' => (string) $question->getKey(),
            'code' => $question->code,
            'stem' => self::safeContent($question->stem, 8000),
            'stem_image_path' => $question->stem_image_path,
            'explanation' => self::safeContent($question->explanation, 12000),
            'key_info' => array_values((array) $question->key_info),
            'attending_tip' => self::safeContent($question->attending_tip, 8000),
            'difficulty' => $question->difficulty->value,
            'status' => $question->status->value,
            'core_clinical_topic_ids' => $question->coreClinicalTopics
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all(),
            'medical_taxonomy_node_ids' => $question->medicalTaxonomyNodes
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all(),
            'medical_taxonomy_links' => $question->medicalTaxonomyNodes
                ->map(fn (MedicalTaxonomyNode $node): array => [
                    'id' => (int) $node->getKey(),
                    'relationship_type' => $node->pivot?->relationship_type,
                    'is_primary' => (bool) ($node->pivot?->is_primary ?? false),
                ])
                ->sortBy('id')
                ->values()
                ->all(),
            'tag_ids' => $question->tags
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all(),
            'hints' => $question->hints
                ->map(fn (QuestionHint $hint): array => [
                    'id' => (int) $hint->getKey(),
                    'content' => self::safeContent($hint->content, 4000),
                    'sort_order' => (int) $hint->sort_order,
                ])
                ->values()
                ->all(),
            'is_free' => (bool) $question->is_free,
            'exam_flag' => (bool) $question->exam_flag,
            'version' => (int) $question->version,
            'created_by' => $question->created_by !== null ? (int) $question->created_by : null,
            'updated_by' => $question->updated_by !== null ? (int) $question->updated_by : null,
            'reviewer_id' => $question->reviewer_id !== null ? (int) $question->reviewer_id : null,
            'rejection_reason' => self::safeContent($question->rejection_reason, 2000),
            'deleted_at' => $question->deleted_at?->toIso8601String(),
            'options' => $question->options
                ->map(fn (QuestionOption $option): array => [
                    'id' => (int) $option->getKey(),
                    'label' => $option->label,
                    'content' => self::safeContent($option->content, 4000),
                    'is_correct' => (bool) $option->is_correct,
                    'explanation' => self::safeContent($option->explanation, 8000),
                    'order' => (int) $option->order,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Normalize a pending editor payload without persisting it.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function questionPayload(array $payload): array
    {
        return [
            'stem' => self::safeContent((string) ($payload['stem'] ?? ''), 8000),
            'stem_image_path' => $payload['stem_image_path'] ?? null,
            'explanation' => self::safeContent($payload['explanation'] ?? null, 12000),
            'key_info' => collect((array) ($payload['key_info'] ?? []))
                ->map(fn (mixed $value): ?string => self::safeContent((string) $value, 4000))
                ->filter()
                ->values()
                ->all(),
            'attending_tip' => self::safeContent($payload['attending_tip'] ?? null, 8000),
            'difficulty' => $payload['difficulty'] ?? null,
            'core_clinical_topic_ids' => self::sortedIds($payload['core_clinical_topic_ids'] ?? []),
            'medical_taxonomy_node_ids' => self::sortedIds($payload['medical_taxonomy_node_ids'] ?? []),
            'medical_taxonomy_links' => collect((array) ($payload['medical_taxonomy_links'] ?? []))
                ->filter(fn (mixed $link): bool => is_array($link) && isset($link['id']))
                ->map(fn (array $link): array => [
                    'id' => (int) $link['id'],
                    'relationship_type' => isset($link['relationship_type'])
                        ? mb_substr(trim((string) $link['relationship_type']), 0, 32)
                        : null,
                    'is_primary' => (bool) ($link['is_primary'] ?? false),
                ])
                ->sortBy('id')
                ->values()
                ->all(),
            'tag_ids' => self::sortedIds($payload['tag_ids'] ?? []),
            'hints' => collect((array) ($payload['hints'] ?? []))
                ->filter(fn (mixed $hint): bool => is_array($hint))
                ->values()
                ->map(fn (array $hint, int $index): array => [
                    'id' => isset($hint['id']) ? (int) $hint['id'] : null,
                    'content' => self::safeContent((string) ($hint['content'] ?? ''), 4000),
                    'sort_order' => (int) ($hint['sort_order'] ?? $index),
                ])
                ->all(),
            'is_free' => (bool) ($payload['is_free'] ?? false),
            'exam_flag' => (bool) ($payload['exam_flag'] ?? false),
            'options' => collect((array) ($payload['options'] ?? []))
                ->filter(fn (mixed $option): bool => is_array($option))
                ->values()
                ->map(fn (array $option, int $index): array => [
                    'id' => isset($option['id']) ? (int) $option['id'] : null,
                    'content' => self::safeContent((string) ($option['content'] ?? ''), 4000),
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                    'explanation' => self::safeContent($option['explanation'] ?? null, 8000),
                    'order' => $index + 1,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function sortedIds(mixed $ids): array
    {
        return collect((array) $ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private static function safeContent(?string $value, int $limit): ?string
    {
        $sanitized = SafeHtml::fromEditor($value);

        if ($sanitized === '') {
            return null;
        }

        return mb_substr($sanitized, 0, $limit);
    }
}
