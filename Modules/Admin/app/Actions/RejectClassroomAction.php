<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use Modules\Admin\Support\Auditor;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Models\Classroom;

/** Admin oversight: reject a pending instructor classroom. */
final class RejectClassroomAction
{
    use AsAction;

    public function handle(User $actor, Classroom $classroom): Classroom
    {
        abort_unless($actor->can(Permission::ClassroomOversee->value), 403);

        abort_unless(
            $classroom->status === ClassroomStatus::PendingApproval,
            422,
            'Chỉ từ chối lớp đang chờ duyệt.',
        );

        $before = ['status' => $classroom->status->value];

        $classroom->update(['status' => ClassroomStatus::Archived]);

        Auditor::record(
            action: 'classroom.reject',
            actor: $actor,
            auditable: $classroom,
            before: $before,
            after: ['status' => ClassroomStatus::Archived->value],
        );

        return $classroom->fresh() ?? $classroom;
    }
}
