<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use Modules\Admin\Support\Auditor;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Models\Classroom;

/** Admin oversight: publish an instructor classroom to learners. */
final class ApproveClassroomAction
{
    use AsAction;

    public function handle(User $actor, Classroom $classroom): Classroom
    {
        abort_unless($actor->can(Permission::ClassroomOversee->value), 403);

        if ($classroom->status === ClassroomStatus::Active) {
            return $classroom;
        }

        abort_unless(
            $classroom->status === ClassroomStatus::PendingApproval,
            422,
            'Chỉ duyệt lớp đang chờ duyệt.',
        );

        $before = ['status' => $classroom->status->value];

        $classroom->update(['status' => ClassroomStatus::Active]);

        Auditor::record(
            action: 'classroom.approve',
            actor: $actor,
            auditable: $classroom,
            before: $before,
            after: ['status' => ClassroomStatus::Active->value],
        );

        return $classroom->fresh() ?? $classroom;
    }
}
