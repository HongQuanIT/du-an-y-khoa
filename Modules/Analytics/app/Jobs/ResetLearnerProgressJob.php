<?php

declare(strict_types=1);

namespace Modules\Analytics\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Analytics\Actions\ResetLearnerProgressAction;

final class ResetLearnerProgressJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<string>  $questionIds
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $questionIds = [],
    ) {}

    public function handle(ResetLearnerProgressAction $action): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $action->wipe($user, $this->questionIds);
    }
}
