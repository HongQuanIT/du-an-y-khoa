<?php

declare(strict_types=1);

namespace Modules\Media\Actions;

use App\Support\Concerns\AsAction;
use Illuminate\Database\Eloquent\Model;
use Modules\Media\Models\MediaUsage;

final class SyncMediaUsagesAction
{
    use AsAction;

    /**
     * @param  list<int|string|null>  $mediaIds
     */
    public function handle(Model $usable, array $mediaIds): void
    {
        $ids = collect($mediaIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $usableType = $usable->getMorphClass();
        $usableId = (int) $usable->getKey();

        MediaUsage::query()
            ->where('usable_type', $usableType)
            ->where('usable_id', $usableId)
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereNotIn('media_id', $ids->all()))
            ->delete();

        foreach ($ids as $mediaId) {
            MediaUsage::query()->firstOrCreate([
                'media_id' => $mediaId,
                'usable_type' => $usableType,
                'usable_id' => $usableId,
            ]);
        }
    }
}
