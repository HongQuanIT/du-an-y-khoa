<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Permission;
use Modules\Admin\Support\Auditor;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomApprovalStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Notification\Actions\CreateUserNotificationAction;

/** Admin oversight: reject a pending instructor classroom. */
final class RejectClassroomAction
{
    use AsAction;

    public function handle(User $actor, Classroom $classroom): Classroom
    {
        abort_unless($actor->can(Permission::ClassroomOversee->value), 403);

        abort_unless(
            $classroom->approval_status === ClassroomApprovalStatus::Pending,
            422,
            'Chỉ từ chối lớp đang chờ duyệt.',
        );

        $before = ['approval_status' => $classroom->approval_status->value, 'lifecycle_status' => $classroom->lifecycle_status->value];

        $classroom->update([
            'status' => ClassroomStatus::Archived,
            'approval_status' => ClassroomApprovalStatus::Rejected,
        ]);

        Auditor::record(
            action: 'classroom.reject',
            actor: $actor,
            auditable: $classroom,
            before: $before,
            after: ['approval_status' => ClassroomApprovalStatus::Rejected->value, 'lifecycle_status' => $classroom->lifecycle_status->value],
        );

        $host = $classroom->host;
        if ($host !== null) {
            CreateUserNotificationAction::run(
                user: $host,
                type: 'classroom.rejected',
                title: 'Lớp chưa được duyệt',
                body: sprintf('“%s” đã bị từ chối. Vui lòng chỉnh sửa và gửi lại.', $classroom->title),
                data: ['classroom_id' => $classroom->getKey()],
                actionUrl: route('teach.classes.show', $classroom),
            );
        }

        return $classroom->fresh() ?? $classroom;
    }
}
