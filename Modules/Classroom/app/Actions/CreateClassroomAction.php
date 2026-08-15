<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomMember;

final class CreateClassroomAction
{
    use AsAction;

    /**
     * @param  array{title: string, description?: string|null, visibility?: string, purpose?: string, max_members?: int|null}  $data
     */
    public function handle(User $host, array $data): Classroom
    {
        return DB::transaction(function () use ($host, $data): Classroom {
            $visibility = ClassroomVisibility::from($data['visibility'] ?? ClassroomVisibility::Public->value);
            $purpose = ClassroomPurpose::tryFrom((string) ($data['purpose'] ?? ''))
                ?? ClassroomPurpose::CommunityReview;

            $classroom = Classroom::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'host_user_id' => $host->getKey(),
                'purpose' => $purpose,
                'visibility' => $visibility,
                'join_code' => $this->makeJoinCode(),
                'status' => ClassroomStatus::PendingApproval,
                'max_members' => $data['max_members'] ?? null,
            ]);

            ClassroomMember::create([
                'classroom_id' => $classroom->getKey(),
                'user_id' => $host->getKey(),
                'role_in_class' => MemberRole::Host,
                'status' => MemberStatus::Active,
                'joined_at' => now(),
            ]);

            return $classroom->fresh(['host', 'activeMembers']) ?? $classroom;
        });
    }

    private function makeJoinCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (Classroom::query()->where('join_code', $code)->exists());

        return $code;
    }
}
