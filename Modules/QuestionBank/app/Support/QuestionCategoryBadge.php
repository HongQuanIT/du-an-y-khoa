<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Illuminate\Support\Collection;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;

/**
 * Compact category + difficulty chips for the session/exam chrome.
 *
 * Questions often attach many taxonomy nodes (system, specialty, disease,
 * symptoms, concepts…). Dumping them all into one pill breaks the layout.
 * The player only needs a high-level orientation label.
 */
final class QuestionCategoryBadge
{
    /** Prefer the highest-level structural label available on the question. */
    private const TYPE_PRIORITY = [
        'system',
        'specialty',
        'disease',
        'condition',
    ];

    /**
     * @param  Collection<int, MedicalTaxonomyNode>|iterable<MedicalTaxonomyNode>  $nodes
     * @return array{category: string, difficulty: string, difficulty_tone: string}
     */
    public static function resolve(iterable $nodes, Difficulty|string|null $difficulty): array
    {
        $collection = $nodes instanceof Collection ? $nodes : collect($nodes);
        $difficultyEnum = $difficulty instanceof Difficulty
            ? $difficulty
            : (is_string($difficulty) && $difficulty !== '' ? Difficulty::tryFrom($difficulty) : null);

        return [
            'category' => self::categoryLabel($collection),
            'difficulty' => $difficultyEnum?->label() ?? '—',
            'difficulty_tone' => self::difficultyTone($difficultyEnum),
        ];
    }

    /**
     * @param  Collection<int, MedicalTaxonomyNode>  $nodes
     */
    public static function categoryLabel(Collection $nodes): string
    {
        if ($nodes->isEmpty()) {
            return 'Tổng hợp';
        }

        foreach (self::TYPE_PRIORITY as $type) {
            $match = $nodes->first(function (MedicalTaxonomyNode $node) use ($type): bool {
                return (string) ($node->node_type ?? '') === $type;
            });

            if ($match instanceof MedicalTaxonomyNode) {
                return self::shorten((string) $match->name);
            }
        }

        // Prefer the pivot-primary topic when structural types are missing.
        $primary = $nodes->first(function (MedicalTaxonomyNode $node): bool {
            return (bool) ($node->pivot?->is_primary ?? false);
        });

        if ($primary instanceof MedicalTaxonomyNode) {
            return self::shorten((string) $primary->name);
        }

        return self::shorten((string) $nodes->first()?->name ?: 'Tổng hợp');
    }

    public static function difficultyTone(?Difficulty $difficulty): string
    {
        return match ($difficulty) {
            Difficulty::VeryEasy, Difficulty::Easy => 'bg-emerald-50 text-emerald-700',
            Difficulty::Hard, Difficulty::VeryHard => 'bg-amber-50 text-amber-800',
            Difficulty::Medium => 'bg-sky-50 text-sky-800',
            default => 'bg-surface-container-highest text-on-surface-variant',
        };
    }

    private static function shorten(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'Tổng hợp';
        }

        // Drop redundant "Hệ " prefix on very long labels so the chip stays readable.
        if (mb_strlen($name) > 28 && str_starts_with($name, 'Hệ ')) {
            $name = mb_substr($name, 3);
        }

        if (mb_strlen($name) > 32) {
            return mb_substr($name, 0, 31).'…';
        }

        return $name;
    }
}
