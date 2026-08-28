<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Models\Classroom;

final class ReopenClassroomAction
{
    use AsAction;

    public function handle(User $actor, Classroom $classroom): Classroom
    {
        if ($classroom->status === ClassroomStatus::Active) {
            return $classroom;
        }

        if ($classroom->status !== ClassroomStatus::Closed) {
            throw ValidationException::withMessages([
                'classroom' => 'Chỉ có thể mở lại lớp đã đóng.',
            ]);
        }

        $classroom->update(['status' => ClassroomStatus::Active]);

        Auditor::record(
            AuditAction::ClassroomReopened,
            $actor,
            $classroom,
            before: ['status' => ClassroomStatus::Closed->value],
            after: ['status' => ClassroomStatus::Active->value],
        );

        return $classroom->fresh() ?? $classroom;
    }
}
