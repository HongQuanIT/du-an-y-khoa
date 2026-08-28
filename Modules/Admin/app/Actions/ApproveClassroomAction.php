<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use Modules\Admin\Support\Auditor;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomApprovalStatus;
use Modules\Classroom\Enums\ClassroomLifecycleStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Notification\Actions\CreateUserNotificationAction;

/** Admin oversight: publish an instructor classroom to learners. */
final class ApproveClassroomAction
{
    use AsAction;

    public function handle(User $actor, Classroom $classroom): Classroom
    {
        abort_unless($actor->can(Permission::ClassroomOversee->value), 403);

        if ($classroom->approval_status === ClassroomApprovalStatus::Approved
            && $classroom->lifecycle_status === ClassroomLifecycleStatus::Active) {
            return $classroom;
        }

        abort_unless(
            $classroom->approval_status === ClassroomApprovalStatus::Pending,
            422,
            'Chỉ duyệt lớp đang chờ duyệt.',
        );

        $before = ['approval_status' => $classroom->approval_status->value, 'lifecycle_status' => $classroom->lifecycle_status->value];

        $classroom->update([
            'status' => ClassroomStatus::Active,
            'approval_status' => ClassroomApprovalStatus::Approved,
            'lifecycle_status' => ClassroomLifecycleStatus::Active,
        ]);

        Auditor::record(
            action: 'classroom.approve',
            actor: $actor,
            auditable: $classroom,
            before: $before,
            after: ['approval_status' => ClassroomApprovalStatus::Approved->value, 'lifecycle_status' => ClassroomLifecycleStatus::Active->value],
        );

        $host = $classroom->host;
        if ($host !== null) {
            CreateUserNotificationAction::run(
                user: $host,
                type: 'classroom.approved',
                title: 'Lớp đã được duyệt',
                body: sprintf('“%s” đã được phê duyệt và hiện với học viên.', $classroom->title),
                data: ['classroom_id' => $classroom->getKey()],
                actionUrl: route('teach.classes.show', $classroom),
            );
        }

        return $classroom->fresh() ?? $classroom;
    }
}
