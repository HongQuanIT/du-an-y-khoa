<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use App\Support\Enums\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomApprovalStatus;
use Modules\Classroom\Enums\ClassroomLifecycleStatus;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomMember;
use Modules\Notification\Actions\CreateUserNotificationAction;

final class CreateClassroomAction
{
    use AsAction;

    public function __construct(
        private readonly CreateUserNotificationAction $notify,
    ) {}

    /**
     * @param  array{title: string, description?: string|null, visibility?: string, purpose?: string, max_members?: int|null}  $data
     */
    public function handle(User $host, array $data, bool $notifyAdmins = true): Classroom
    {
        $classroom = DB::transaction(function () use ($host, $data): Classroom {
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
                'approval_status' => ClassroomApprovalStatus::Pending,
                'lifecycle_status' => ClassroomLifecycleStatus::Active,
                'max_members' => $data['max_members'] ?? null,
            ]);

            ClassroomMember::create([
                'classroom_id' => $classroom->getKey(),
                'user_id' => $host->getKey(),
                'role_in_class' => MemberRole::Host,
                'status' => MemberStatus::Active,
                'joined_at' => now(),
            ]);

            $classroom = $classroom->fresh(['host', 'activeMembers']) ?? $classroom;
            Auditor::record(
                AuditAction::ClassroomCreated,
                $host,
                $classroom,
                after: $this->snapshot($classroom),
            );

            return $classroom;
        });

        if ($notifyAdmins) {
            $this->notifyAdminsThatClassroomNeedsApproval($host, $classroom);
        }

        return $classroom;
    }

    private function notifyAdminsThatClassroomNeedsApproval(User $host, Classroom $classroom): void
    {
        User::query()
            ->role([Role::Admin->value, Role::SuperAdmin->value])
            ->each(function (User $admin) use ($host, $classroom): void {
                $this->notify->handle(
                    $admin,
                    'classroom.pending_approval',
                    'Có lớp học mới chờ duyệt',
                    "Giảng viên {$host->name} vừa tạo lớp “{$classroom->title}”.",
                    [
                        'classroom_id' => $classroom->getKey(),
                        'host_user_id' => $host->getKey(),
                    ],
                    route('admin.classrooms.show', $classroom),
                );
            });
    }

    private function makeJoinCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (Classroom::query()->where('join_code', $code)->exists());

        return $code;
    }

    /** @return array<string, mixed> */
    private function snapshot(Classroom $classroom): array
    {
        return [
            'id' => $classroom->getKey(),
            'title' => $classroom->title,
            'status' => $classroom->status->value,
            'visibility' => $classroom->visibility->value,
            'purpose' => $classroom->purpose->value,
            'host_user_id' => $classroom->host_user_id,
            'max_members' => $classroom->max_members,
        ];
    }
}
