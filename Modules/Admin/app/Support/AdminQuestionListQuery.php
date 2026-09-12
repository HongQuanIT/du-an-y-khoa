<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Support\QuestionFilterBuilder;

final class AdminQuestionListQuery
{
    /**
     * @param  Builder<Question>  $query
     * @return Builder<Question>
     */
    public function apply(Builder $query, Request $request, User $actor): Builder
    {
        QuestionAccess::scopeVisibleTo($query, $actor);

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('stem', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $statuses = self::stringValues($request->query('status'));
        if ($statuses === [] && $request->query('review') === 'pending') {
            $statuses = [QuestionStatus::InReview->value];
        }
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $difficulties = self::stringValues($request->query('difficulty'));
        if ($difficulties !== []) {
            $query->whereIn('difficulty', $difficulties);
        }

        if ($coreTopicId = $request->query('core_clinical_topic_id')) {
            app(QuestionFilterBuilder::class)
                ->whereMatchesCoreClinicalTopic($query, (int) $coreTopicId);
        }

        if ($lessonId = $request->query('lesson_id')) {
            $query->whereHas('lessons', fn ($q) => $q->whereKey((int) $lessonId));
        }

        if ($tagId = $request->query('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->whereKey((int) $tagId));
        }

        $access = self::stringValues($request->query('is_free'));
        if ($access === ['1']) {
            $query->where('is_free', true);
        } elseif ($access === ['0']) {
            $query->where('is_free', false);
        }

        $creatorIds = self::integerIds($request->query('created_by'));
        if ($creatorIds !== []) {
            $query->whereIn('created_by', $creatorIds);
        }

        if ($batchId = $request->query('import_batch_id')) {
            $query->where('import_batch_id', (string) $batchId);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public static function stringValues(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value) && str_contains($value, ',')) {
            $value = explode(',', $value);
        }

        return collect(is_array($value) ? $value : [$value])
            ->flatten()
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function integerIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value) && str_contains($value, ',')) {
            $value = explode(',', $value);
        }

        return collect(is_array($value) ? $value : [$value])
            ->flatten()
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
