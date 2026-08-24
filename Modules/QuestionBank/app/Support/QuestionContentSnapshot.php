<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionHint;

/**
 * Build a JSON snapshot of question content at approval time.
 */
final class QuestionContentSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function fromQuestion(Question $question): array
    {
        $question->loadMissing([
            'options' => fn ($q) => $q->orderBy('order'),
            'hints',
            'coreClinicalTopics',
            'medicalTaxonomyNodes',
            'tags',
        ]);

        return [
            'code' => $question->code,
            'stem' => $question->stem,
            'stem_image_path' => $question->stem_image_path,
            'explanation' => $question->explanation,
            'key_info' => $question->key_info,
            'hints' => $question->hints->map(fn (QuestionHint $hint): array => [
                'id' => $hint->id,
                'content' => $hint->content,
                'sort_order' => $hint->sort_order,
            ])->values()->all(),
            'attending_tip' => $question->attending_tip,
            'difficulty' => $question->difficulty->value,
            'core_clinical_topic_ids' => $question->coreClinicalTopics->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'medical_taxonomy_node_ids' => $question->medicalTaxonomyNodes->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'medical_taxonomy_node_names' => $question->medicalTaxonomyNodes->pluck('name')->values()->all(),
            'tag_ids' => $question->tags->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'is_free' => $question->is_free,
            'exam_flag' => $question->exam_flag,
            'status' => $question->status->value,
            'options' => $question->options->map(fn ($option): array => [
                'id' => $option->id,
                'label' => $option->label,
                'content' => $option->content,
                'is_correct' => (bool) $option->is_correct,
                'explanation' => $option->explanation,
                'order' => $option->order,
            ])->values()->all(),
        ];
    }
}
