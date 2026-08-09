<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use Modules\Admin\Support\Auditor;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Models\Classroom;

/**
 * Admin oversight: archive any classroom.
 */
final class ArchiveClassroomAction
{
    use AsAction;

    public function handle(User $actor, Classroom $classroom): Classroom
    {
        abort_unless($actor->can(Permission::ClassroomOversee->value), 403);

        if ($classroom->status === ClassroomStatus::Archived) {
            return $classroom;
        }

        $before = ['status' => $classroom->status->value];

        $classroom->update(['status' => ClassroomStatus::Archived]);

        Auditor::record(
            action: 'classroom.archive',
            actor: $actor,
            auditable: $classroom,
            before: $before,
            after: ['status' => ClassroomStatus::Archived->value],
        );

        return $classroom->fresh() ?? $classroom;
    }
}
