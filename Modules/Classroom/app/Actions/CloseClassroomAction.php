<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Models\Classroom;

final class CloseClassroomAction
{
    use AsAction;

    public function handle(User $actor, Classroom $classroom): Classroom
    {
        if ($classroom->status === ClassroomStatus::Closed) {
            return $classroom;
        }

        if ($classroom->status !== ClassroomStatus::Active) {
            throw ValidationException::withMessages([
                'classroom' => 'Chỉ có thể đóng lớp đang hoạt động.',
            ]);
        }

        if ($classroom->liveSession()->exists()) {
            throw ValidationException::withMessages([
                'classroom' => 'Hãy kết thúc buổi live trước khi đóng lớp.',
            ]);
        }

        if (! $classroom->sessions()->where('status', LiveSessionStatus::Ended->value)->exists()) {
            throw ValidationException::withMessages([
                'classroom' => 'Lớp chưa có buổi live nào kết thúc.',
            ]);
        }

        $classroom->update(['status' => ClassroomStatus::Closed]);

        Auditor::record(
            AuditAction::ClassroomClosed,
            $actor,
            $classroom,
            before: ['status' => ClassroomStatus::Active->value],
            after: ['status' => ClassroomStatus::Closed->value],
        );

        return $classroom->fresh() ?? $classroom;
    }
}
