<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use Modules\Admin\Support\Auditor;
use Modules\Classroom\Actions\EndLiveSessionAction;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;

/**
 * Admin oversight: force-end the current live session on a classroom.
 */
final class ForceEndClassroomLiveAction
{
    use AsAction;

    public function __construct(private readonly EndLiveSessionAction $endLive) {}

    public function handle(User $actor, Classroom $classroom): ?LiveSession
    {
        abort_unless($actor->can(Permission::LiveForceEnd->value)
            || $actor->can(Permission::ClassroomOversee->value), 403);

        /** @var LiveSession|null $session */
        $session = $classroom->sessions()
            ->where('status', LiveSessionStatus::Live->value)
            ->latest('id')
            ->first();

        if ($session === null) {
            return null;
        }

        $ended = $this->endLive->handle($classroom, $session);

        Auditor::record(
            action: 'classroom.live.force_end',
            actor: $actor,
            auditable: $ended,
            before: ['status' => LiveSessionStatus::Live->value],
            after: ['status' => $ended->status->value, 'classroom_id' => $classroom->getKey()],
        );

        return $ended;
    }
}
