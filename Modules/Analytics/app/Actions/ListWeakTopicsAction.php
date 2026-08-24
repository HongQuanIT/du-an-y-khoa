<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Collection;
use Modules\Analytics\Models\TopicMastery;

/**
 * Use case: the taxonomy nodes a learner is weakest at (srs/modules/20).
 */
final class ListWeakTopicsAction
{
    use AsAction;

    /** Ignore nodes with too little history to judge. */
    private const MIN_ATTEMPTS = 3;

    /**
     * @return Collection<int, array{name: string, accuracy: int}>
     */
    public function handle(User $user, int $limit = 3): Collection
    {
        return TopicMastery::query()
            ->with('medicalTaxonomyNode')
            ->where('user_id', $user->getKey())
            ->where('attempts', '>=', self::MIN_ATTEMPTS)
            ->orderBy('correct_rate')
            ->limit($limit)
            ->get()
            ->map(fn (TopicMastery $mastery) => [
                'name' => $mastery->medicalTaxonomyNode?->name ?? 'Không rõ',
                'accuracy' => (int) round($mastery->correct_rate),
            ]);
    }
}
