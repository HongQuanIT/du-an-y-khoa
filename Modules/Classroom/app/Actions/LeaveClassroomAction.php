<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;

final class LeaveClassroomAction
{
    use AsAction;

    public function handle(User $user, Classroom $classroom): void
    {
        $member = $classroom->memberFor($user);

        if ($member === null || $member->role_in_class === MemberRole::Host) {
            return;
        }

        $member->update(['status' => MemberStatus::Left]);
        Auditor::record(
            AuditAction::ClassroomLeft,
            $user,
            $classroom,
            metadata: ['classroom_member_id' => $member->getKey()],
        );
    }
}
