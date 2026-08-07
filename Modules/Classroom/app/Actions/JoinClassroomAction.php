<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomMember;

final class JoinClassroomAction
{
    use AsAction;

    public function handle(User $user, Classroom $classroom, ?string $joinCode = null): ClassroomMember
    {
        $existing = $classroom->memberFor($user);

        if ($existing?->status === MemberStatus::Banned) {
            throw ValidationException::withMessages([
                'classroom' => 'Bạn đã bị cấm khỏi lớp này.',
            ]);
        }

        if ($existing?->status === MemberStatus::Active) {
            return $existing;
        }

        if ($classroom->visibility === ClassroomVisibility::InviteOnly
            && $existing?->status !== MemberStatus::Invited) {
            throw ValidationException::withMessages([
                'classroom' => 'Lớp chỉ nhận thành viên được mời.',
            ]);
        }

        if (in_array($classroom->visibility, [ClassroomVisibility::Unlisted, ClassroomVisibility::InviteOnly], true)
            && $joinCode !== null
            && $joinCode !== ''
            && strcasecmp($joinCode, (string) $classroom->join_code) !== 0) {
            throw ValidationException::withMessages([
                'join_code' => 'Mã tham gia không đúng.',
            ]);
        }

        if ($classroom->visibility === ClassroomVisibility::Unlisted
            && ($joinCode === null || $joinCode === '')
            && $existing?->status !== MemberStatus::Invited) {
            throw ValidationException::withMessages([
                'join_code' => 'Cần mã tham gia để vào lớp không liệt kê.',
            ]);
        }

        if ($classroom->max_members !== null
            && $classroom->activeMembers()->count() >= $classroom->max_members) {
            throw ValidationException::withMessages([
                'classroom' => 'Lớp đã đủ số thành viên.',
            ]);
        }

        if ($existing !== null) {
            $existing->update([
                'status' => MemberStatus::Active,
                'role_in_class' => $existing->role_in_class === MemberRole::Host
                    ? MemberRole::Host
                    : MemberRole::Member,
                'joined_at' => $existing->joined_at ?? now(),
            ]);

            return $existing->fresh() ?? $existing;
        }

        return ClassroomMember::create([
            'classroom_id' => $classroom->getKey(),
            'user_id' => $user->getKey(),
            'role_in_class' => MemberRole::Member,
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);
    }
}
