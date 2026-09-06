<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionVersion;

/**
 * QBank serves the last published snapshot while working copy is in review.
 */
final class ServePublishedQuestion
{
    /** @param  Builder<Question>  $query */
    public static function scopeAvailable(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder->where('status', QuestionStatus::Published)
                ->orWhere(function (Builder $revision): void {
                    $revision->whereNotNull('published_version')
                        ->where('published_version', '>', 0)
                        ->whereNotIn('status', [
                            QuestionStatus::Retired,
                            QuestionStatus::Private,
                        ]);
                });
        });
    }

    public static function isAvailable(Question $question): bool
    {
        if ($question->status === QuestionStatus::Published) {
            return true;
        }

        return $question->published_version !== null
            && (int) $question->published_version > 0
            && ! in_array($question->status, [QuestionStatus::Retired, QuestionStatus::Private], true);
    }

    public static function needsOverlay(Question $question): bool
    {
        return self::isAvailable($question)
            && $question->status !== QuestionStatus::Published;
    }

    public static function publishedIsFree(Question $question): bool
    {
        if (! self::needsOverlay($question)) {
            return (bool) $question->is_free;
        }

        $version = QuestionVersion::query()
            ->where('question_id', $question->getKey())
            ->where('version', (int) $question->published_version)
            ->first();

        if ($version === null) {
            return (bool) $question->is_free;
        }

        return (bool) (($version->snapshot['is_free'] ?? $question->is_free));
    }

    public static function overlay(Question $question): Question
    {
        if (! self::needsOverlay($question)) {
            return $question;
        }

        $version = QuestionVersion::query()
            ->where('question_id', $question->getKey())
            ->where('version', (int) $question->published_version)
            ->first();

        if ($version === null) {
            return $question;
        }

        $question->loadMissing(['options' => fn ($query) => $query->orderBy('order')]);

        return self::applySnapshot($question, $version->snapshot ?? []);
    }

    /**
     * @param  Collection<int, Question>|iterable<Question>  $questions
     * @return Collection<int, Question>
     */
    public static function overlayMany(iterable $questions): Collection
    {
        $collection = Collection::make($questions)->values();
        $needs = $collection->filter(fn (Question $question): bool => self::needsOverlay($question));
        if ($needs->isEmpty()) {
            return $collection;
        }

        $versions = QuestionVersion::query()
            ->whereIn('question_id', $needs->map(fn (Question $q) => $q->getKey())->all())
            ->whereIn('version', $needs->map(fn (Question $q) => (int) $q->published_version)->unique()->all())
            ->get()
            ->groupBy('question_id');

        foreach ($needs as $question) {
            $version = ($versions->get($question->getKey()) ?? collect())
                ->firstWhere('version', (int) $question->published_version);
            if ($version !== null) {
                $question->loadMissing(['options' => fn ($query) => $query->orderBy('order')]);
                self::applySnapshot($question, $version->snapshot ?? []);
            }
        }

        return $collection;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function applySnapshot(Question $question, array $snapshot): Question
    {
        if ($snapshot === []) {
            return $question;
        }

        $question->forceFill([
            'stem' => (string) ($snapshot['stem'] ?? $question->stem),
            'stem_image_path' => $snapshot['stem_image_path'] ?? $question->stem_image_path,
            'explanation' => $snapshot['explanation'] ?? $question->explanation,
            'key_info' => array_values((array) ($snapshot['key_info'] ?? $question->key_info ?? [])),
            'attending_tip' => $snapshot['attending_tip'] ?? $question->attending_tip,
            'difficulty' => Difficulty::tryFrom((string) ($snapshot['difficulty'] ?? '')) ?? $question->difficulty,
            'is_free' => (bool) ($snapshot['is_free'] ?? $question->is_free),
            'exam_flag' => (bool) ($snapshot['exam_flag'] ?? $question->exam_flag),
        ]);
        $question->syncOriginal();

        $liveById = $question->relationLoaded('options')
            ? $question->options->keyBy(fn (QuestionOption $o): int => (int) $o->getKey())
            : collect();
        $liveByContentOrder = $question->relationLoaded('options')
            ? $question->options->keyBy(
                fn (QuestionOption $o): string => self::optionMatchKey((string) $o->content, (int) $o->order),
            )
            : collect();

        $options = collect((array) ($snapshot['options'] ?? []))
            ->values()
            ->map(function (array $row, int $index) use ($question, $liveById, $liveByContentOrder): QuestionOption {
                $content = (string) ($row['content'] ?? '');
                $order = (int) ($row['order'] ?? ($index + 1));
                $option = new QuestionOption([
                    'question_id' => $question->getKey(),
                    'label' => (string) ($row['label'] ?? chr(65 + $index)),
                    'content' => $content,
                    'is_correct' => (bool) ($row['is_correct'] ?? false),
                    'explanation' => $row['explanation'] ?? null,
                    'order' => $order,
                ]);

                $resolvedId = isset($row['id']) ? (int) $row['id'] : null;
                if ($resolvedId === null || $resolvedId <= 0) {
                    $match = $liveByContentOrder->get(self::optionMatchKey($content, $order));
                    $resolvedId = $match instanceof QuestionOption ? (int) $match->getKey() : null;
                } elseif (! $liveById->has($resolvedId) && $liveByContentOrder->isNotEmpty()) {
                    // Snapshot id may be stale after option recreate — fall back to content+order.
                    $match = $liveByContentOrder->get(self::optionMatchKey($content, $order));
                    if ($match instanceof QuestionOption) {
                        $resolvedId = (int) $match->getKey();
                    }
                }

                if ($resolvedId !== null && $resolvedId > 0) {
                    $option->id = $resolvedId;
                    $option->exists = true;
                }

                return $option;
            });

        $question->setRelation('options', $options);

        return $question;
    }

    private static function optionMatchKey(string $content, int $order): string
    {
        return $order.'|'.md5($content);
    }
}
