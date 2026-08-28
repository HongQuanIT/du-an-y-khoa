<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use Modules\Admin\Support\Auditor;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomLifecycleStatus;
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

        if ($classroom->lifecycle_status === ClassroomLifecycleStatus::Archived) {
            return $classroom;
        }

        $before = ['lifecycle_status' => $classroom->lifecycle_status->value];

        $classroom->update(['status' => ClassroomStatus::Archived, 'lifecycle_status' => ClassroomLifecycleStatus::Archived]);

        Auditor::record(
            action: 'classroom.archive',
            actor: $actor,
            auditable: $classroom,
            before: $before,
            after: ['lifecycle_status' => ClassroomLifecycleStatus::Archived->value],
        );

        return $classroom->fresh() ?? $classroom;
    }
}
