<?php

declare(strict_types=1);

namespace Modules\Analytics\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Collection;
use Modules\Analytics\Models\TopicMastery;

/**
 * Use case: the topics a learner is weakest at (srs/modules/20).
 */
final class ListWeakTopicsAction
{
    use AsAction;

    /** Ignore topics with too little history to judge. */
    private const MIN_ATTEMPTS = 3;

    /**
     * @return Collection<int, array{name: string, accuracy: int}>
     */
    public function handle(User $user, int $limit = 3): Collection
    {
        return TopicMastery::query()
            ->with('topic')
            ->where('user_id', $user->getKey())
            ->where('attempts', '>=', self::MIN_ATTEMPTS)
            ->orderBy('correct_rate')
            ->limit($limit)
            ->get()
            ->map(fn (TopicMastery $mastery) => [
                'name' => $mastery->topic->name,
                'accuracy' => (int) round($mastery->correct_rate),
            ]);
    }
}
