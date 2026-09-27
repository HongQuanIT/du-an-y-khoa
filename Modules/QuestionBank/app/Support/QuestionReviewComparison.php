<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use App\Support\Html\SafeHtml;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Models\Profession;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Models\Blueprint;
use Modules\QuestionBank\Models\Lesson;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionVersion;

/**
 * Side-by-side comparison of the working copy vs the live published snapshot.
 *
 * @phpstan-type TextField array{changed: bool, published_html: string, proposed_html: string}
 * @phpstan-type Chip array{label: string, change: 'same'|'added'|'removed'}
 * @phpstan-type OptionSide array{label: string, content_html: string, explanation_html: string, is_correct: bool}
 * @phpstan-type OptionRow array{change: 'same'|'modified'|'added'|'removed', published: ?OptionSide, proposed: ?OptionSide, correct_changed: bool}
 */
final class QuestionReviewComparison
{
    public function __construct(
        private readonly QuestionTextDiff $diff,
    ) {}

    /**
     * @return array{
     *     can_compare: bool,
     *     published_version: int|null,
     *     has_changes: bool,
     *     changed_labels: list<string>,
     *     stem: TextField,
     *     attending_tip: TextField,
     *     difficulty: array{changed: bool, published: string, proposed: string},
     *     stem_image: array{changed: bool, published_url: string|null, proposed_url: string|null},
     *     lessons: array{changed: bool, published: list<Chip>, proposed: list<Chip>},
     *     professions: array{changed: bool, published: list<Chip>, proposed: list<Chip>},
     *     blueprints: array{changed: bool, published: list<Chip>, proposed: list<Chip>},
     *     access: array{changed: bool, published: string, proposed: string},
     *     key_info: array{changed: bool, published: list<array{html: string, change: string}>, proposed: list<array{html: string, change: string}>},
     *     options: list<OptionRow>
     * }
     */
    public function compare(Question $question): array
    {
        $question->loadMissing([
            'options' => fn ($query) => $query->orderBy('order'),
            'lessons:id,name',
            'professions:id,name',
            'blueprints:id,name',
        ]);

        $publishedVersion = (int) ($question->published_version ?? 0);
        $version = $publishedVersion >= 1
            ? QuestionVersion::query()
                ->where('question_id', $question->getKey())
                ->where('version', $publishedVersion)
                ->first()
            : null;

        $snapshot = is_array($version?->snapshot) ? $version->snapshot : [];
        $canCompare = $version !== null;
        $stem = $this->diff->highlight(
            (string) ($snapshot['stem'] ?? ''),
            (string) $question->stem,
        );
        $attendingTip = $this->diff->highlight(
            (string) ($snapshot['attending_tip'] ?? ''),
            (string) $question->attending_tip,
        );

        $publishedDifficulty = Difficulty::tryFrom((string) ($snapshot['difficulty'] ?? ''));
        $difficultyChanged = ($publishedDifficulty?->value ?? '') !== $question->difficulty->value;

        $publishedImage = is_string($snapshot['stem_image_path'] ?? null)
            ? $snapshot['stem_image_path']
            : null;
        $proposedImage = $question->stem_image_path;
        $imageChanged = (string) $publishedImage !== (string) $proposedImage;

        $lessons = $this->compareLessons($snapshot, $question);
        $professions = $this->compareMembership($snapshot, $question, 'profession_ids', 'professions', Profession::class, $canCompare, 'Đối tượng #');
        $blueprints = $this->compareMembership($snapshot, $question, 'blueprint_ids', 'blueprints', Blueprint::class, $canCompare, 'Kỳ thi #');
        $access = $this->compareAccess($snapshot, $question, $canCompare);
        $keyInfo = $this->compareKeyInfo(
            array_values((array) ($snapshot['key_info'] ?? [])),
            array_values((array) ($question->key_info ?? [])),
        );
        $options = $this->compareOptions(
            array_values((array) ($snapshot['options'] ?? [])),
            $question->options,
        );

        $changedLabels = [];
        if ($stem['changed']) {
            $changedLabels[] = 'Câu hỏi';
        }
        if ($imageChanged) {
            $changedLabels[] = 'Hình ảnh';
        }
        if ($lessons['changed']) {
            $changedLabels[] = 'Bài học';
        }
        if ($professions['changed']) {
            $changedLabels[] = 'Đối tượng';
        }
        if ($blueprints['changed']) {
            $changedLabels[] = 'Kỳ thi';
        }
        if ($difficultyChanged) {
            $changedLabels[] = 'Độ khó';
        }
        if ($access['changed']) {
            $changedLabels[] = 'Truy cập';
        }
        if (collect($options)->contains(fn (array $row): bool => $row['change'] !== 'same')) {
            $changedLabels[] = 'Đáp án';
        }
        if ($keyInfo['changed']) {
            $changedLabels[] = 'Gợi ý';
        }
        if ($attendingTip['changed']) {
            $changedLabels[] = 'Kiến thức';
        }

        if (! $canCompare) {
            $changedLabels = ['Câu mới'];
        }

        return [
            'can_compare' => $canCompare,
            'published_version' => $canCompare ? $publishedVersion : null,
            'has_changes' => $changedLabels !== [],
            'changed_labels' => $changedLabels,
            'stem' => $stem,
            'raw_stem' => [
                'published_html' => SafeHtml::forDisplay((string) ($snapshot['stem'] ?? '')),
                'proposed_html' => SafeHtml::forDisplay((string) $question->stem),
            ],
            'raw_attending_tip' => [
                'published_html' => SafeHtml::forDisplay((string) ($snapshot['attending_tip'] ?? '')),
                'proposed_html' => SafeHtml::forDisplay((string) $question->attending_tip),
            ],
            'attending_tip' => $attendingTip,
            'difficulty' => [
                'changed' => $difficultyChanged,
                'published' => $publishedDifficulty?->label() ?? '—',
                'proposed' => $question->difficulty->label(),
            ],
            'stem_image' => [
                'changed' => $imageChanged,
                'published_url' => $this->imageUrl($publishedImage),
                'proposed_url' => $this->imageUrl($proposedImage),
            ],
            'lessons' => $lessons,
            'professions' => $professions,
            'blueprints' => $blueprints,
            'access' => $access,
            'key_info' => $keyInfo,
            'options' => $options,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{changed: bool, published: list<Chip>, proposed: list<Chip>}
     */
    private function compareLessons(array $snapshot, Question $question): array
    {
        $publishedIds = collect($snapshot['lesson_ids'] ?? $snapshot['medical_taxonomy_node_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $proposedIds = $question->lessons
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $names = Lesson::query()
            ->whereIn('id', $publishedIds->merge($proposedIds)->unique()->all())
            ->pluck('name', 'id');

        $publishedSet = $publishedIds->flip();
        $proposedSet = $proposedIds->flip();

        $published = $publishedIds->map(fn (int $id): array => [
            'label' => (string) ($names[$id] ?? "Bài học #{$id}"),
            'change' => $proposedSet->has($id) ? 'same' : 'removed',
        ])->all();

        $proposed = $proposedIds->map(fn (int $id): array => [
            'label' => (string) ($names[$id] ?? $question->lessons->firstWhere('id', $id)?->name ?? "Bài học #{$id}"),
            'change' => $publishedSet->has($id) ? 'same' : 'added',
        ])->all();

        return [
            'changed' => $publishedIds->sort()->values()->all() !== $proposedIds->sort()->values()->all(),
            'published' => $published,
            'proposed' => $proposed,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  class-string<Profession|Blueprint>  $modelClass
     * @return array{changed: bool, published: list<Chip>, proposed: list<Chip>}
     */
    private function compareMembership(
        array $snapshot,
        Question $question,
        string $snapshotKey,
        string $relation,
        string $modelClass,
        bool $canCompare,
        string $missingLabel,
    ): array {
        $proposedIds = $question->{$relation}
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if (! $canCompare) {
            $publishedIds = collect();
        } elseif (! array_key_exists($snapshotKey, $snapshot)) {
            $publishedIds = $proposedIds;
        } else {
            $publishedIds = collect($snapshot[$snapshotKey] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values();
        }

        $names = $modelClass::query()
            ->whereIn('id', $publishedIds->merge($proposedIds)->unique()->all())
            ->pluck('name', 'id');

        $publishedSet = $publishedIds->flip();
        $proposedSet = $proposedIds->flip();
        $label = fn (int $id): string => (string) ($names[$id] ?? $missingLabel.$id);

        $published = $publishedIds->map(fn (int $id): array => [
            'label' => $label($id),
            'change' => $proposedSet->has($id) ? 'same' : 'removed',
        ])->all();

        $proposed = $proposedIds->map(fn (int $id): array => [
            'label' => $label($id),
            'change' => $publishedSet->has($id) ? 'same' : 'added',
        ])->all();

        return [
            'changed' => $publishedIds->sort()->values()->all() !== $proposedIds->sort()->values()->all(),
            'published' => $published,
            'proposed' => $proposed,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{changed: bool, published: string, proposed: string}
     */
    private function compareAccess(array $snapshot, Question $question, bool $canCompare): array
    {
        $proposedFree = (bool) $question->is_free;
        $proposed = $proposedFree ? 'Miễn phí' : 'Chỉ Premium';

        if (! $canCompare) {
            return [
                'changed' => true,
                'published' => '—',
                'proposed' => $proposed,
            ];
        }

        if (! array_key_exists('is_free', $snapshot)) {
            return [
                'changed' => false,
                'published' => $proposed,
                'proposed' => $proposed,
            ];
        }

        $publishedFree = (bool) $snapshot['is_free'];

        return [
            'changed' => $publishedFree !== $proposedFree,
            'published' => $publishedFree ? 'Miễn phí' : 'Chỉ Premium',
            'proposed' => $proposed,
        ];
    }

    /**
     * @param  list<mixed>  $published
     * @param  list<mixed>  $proposed
     * @return array{changed: bool, published: list<array{html: string, change: string}>, proposed: list<array{html: string, change: string}>}
     */
    private function compareKeyInfo(array $published, array $proposed): array
    {
        $left = array_values(array_filter(
            array_map(fn ($item): string => $this->diff->normalize(is_scalar($item) ? (string) $item : ''), $published),
            fn (string $item): bool => $item !== '',
        ));
        $right = array_values(array_filter(
            array_map(fn ($item): string => $this->diff->normalize(is_scalar($item) ? (string) $item : ''), $proposed),
            fn (string $item): bool => $item !== '',
        ));

        $usedRight = [];
        $publishedRows = [];

        foreach ($left as $item) {
            $match = null;
            foreach ($right as $index => $candidate) {
                if (isset($usedRight[$index])) {
                    continue;
                }
                if ($candidate === $item) {
                    $match = $index;
                    break;
                }
            }

            if ($match === null) {
                $publishedRows[] = [
                    'html' => $this->diff->highlight($item, '')['published_html'],
                    'change' => 'removed',
                ];

                continue;
            }

            $usedRight[$match] = true;
            $publishedRows[] = ['html' => e($item), 'change' => 'same'];
        }

        $proposedRows = [];
        foreach ($right as $index => $item) {
            if (isset($usedRight[$index])) {
                $proposedRows[] = ['html' => e($item), 'change' => 'same'];

                continue;
            }

            $proposedRows[] = [
                'html' => $this->diff->highlight('', $item)['proposed_html'],
                'change' => 'added',
            ];
        }

        return [
            'changed' => $left !== $right,
            'published' => $publishedRows,
            'proposed' => $proposedRows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $published
     * @param  Collection<int, QuestionOption>  $proposed
     * @return list<OptionRow>
     */
    private function compareOptions(array $published, Collection $proposed): array
    {
        $proposedRows = $proposed->values();
        $used = [];
        $pairs = [];

        foreach ($published as $row) {
            $matchIndex = $this->matchProposedOption($row, $proposedRows, $used);
            if ($matchIndex === null) {
                $pairs[] = ['published' => $row, 'proposed' => null];

                continue;
            }

            $used[$matchIndex] = true;
            $pairs[] = ['published' => $row, 'proposed' => $proposedRows->get($matchIndex)];
        }

        foreach ($proposedRows as $index => $option) {
            if (isset($used[$index])) {
                continue;
            }
            $pairs[] = ['published' => null, 'proposed' => $option];
        }

        $rows = [];
        $letter = 0;
        foreach ($pairs as $pair) {
            $rows[] = $this->optionRow($pair['published'], $pair['proposed'], $letter);
            $letter++;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $published
     * @param  Collection<int, QuestionOption>  $proposed
     * @param  array<int, true>  $used
     */
    private function matchProposedOption(array $published, Collection $proposed, array $used): ?int
    {
        $publishedId = isset($published['id']) ? (int) $published['id'] : 0;
        if ($publishedId > 0) {
            foreach ($proposed as $index => $option) {
                if (isset($used[$index])) {
                    continue;
                }
                if ((int) $option->getKey() === $publishedId) {
                    return $index;
                }
            }
        }

        $content = $this->diff->normalize((string) ($published['content'] ?? ''));
        $order = (int) ($published['order'] ?? 0);
        foreach ($proposed as $index => $option) {
            if (isset($used[$index])) {
                continue;
            }
            if ($this->diff->normalize((string) $option->content) === $content
                && (int) $option->order === $order) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $published
     * @return OptionRow
     */
    private function optionRow(?array $published, ?QuestionOption $proposed, int $index): array
    {
        $label = chr(65 + $index);
        $publishedContent = is_array($published) ? (string) ($published['content'] ?? '') : '';
        $proposedContent = $proposed?->content ?? '';
        $publishedExplanation = is_array($published) ? (string) ($published['explanation'] ?? '') : '';
        $proposedExplanation = $proposed?->explanation ?? '';
        $content = $this->diff->highlight($publishedContent, $proposedContent);
        $explanation = $this->diff->highlight($publishedExplanation, $proposedExplanation);
        $publishedCorrect = is_array($published) && (bool) ($published['is_correct'] ?? false);
        $proposedCorrect = (bool) ($proposed?->is_correct);
        $correctChanged = $published !== null && $proposed !== null && $publishedCorrect !== $proposedCorrect;

        $change = match (true) {
            $published === null => 'added',
            $proposed === null => 'removed',
            $content['changed'] || $explanation['changed'] || $correctChanged => 'modified',
            default => 'same',
        };

        return [
            'change' => $change,
            'correct_changed' => $correctChanged,
            'published' => $published === null ? null : [
                'label' => $label,
                'content_html' => $content['published_html'],
                'explanation_html' => $explanation['published_html'],
                'is_correct' => $publishedCorrect,
            ],
            'proposed' => $proposed === null ? null : [
                'label' => $label,
                'content_html' => $content['proposed_html'],
                'explanation_html' => $explanation['proposed_html'],
                'is_correct' => $proposedCorrect,
            ],
        ];
    }

    private function imageUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/storage/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
