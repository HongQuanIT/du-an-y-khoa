<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Modules\Classroom\Enums\ClassroomPurpose;
use Modules\Classroom\Enums\ClassroomStatus;
use Modules\Classroom\Enums\ClassroomVisibility;
use Modules\Classroom\Models\Classroom;

final class UpdateTeachClassroomAction
{
    use AsAction;

    /** @var list<string> */
    private const SIGNIFICANT_FIELDS = ['title', 'description', 'purpose', 'visibility'];

    /**
     * @param  array{title: string, description?: string|null, purpose: string, visibility: string, max_members?: int|null}  $data
     */
    public function handle(User $actor, Classroom $classroom, array $data): Classroom
    {
        return DB::transaction(function () use ($actor, $classroom, $data): Classroom {
            $before = $this->snapshot($classroom);
            $updates = [
                'title' => trim($data['title']),
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'purpose' => ClassroomPurpose::from($data['purpose']),
                'visibility' => ClassroomVisibility::from($data['visibility']),
                'max_members' => $data['max_members'] ?? null,
            ];

            $significantChange = $this->hasSignificantChange($classroom, $updates);
            if ($significantChange && in_array($classroom->status, [ClassroomStatus::Active, ClassroomStatus::Closed], true)) {
                $updates['status'] = ClassroomStatus::PendingApproval;
            }

            $classroom->update($updates);
            $classroom = $classroom->fresh() ?? $classroom;

            Auditor::record(
                AuditAction::ClassroomUpdated,
                $actor,
                $classroom,
                before: $before,
                after: $this->snapshot($classroom) + [
                    'significant_change' => $significantChange,
                    'approval_required' => $classroom->status === ClassroomStatus::PendingApproval
                        && $before['status'] !== ClassroomStatus::PendingApproval->value,
                ],
            );

            return $classroom;
        });
    }

    /** @param array<string, mixed> $updates */
    private function hasSignificantChange(Classroom $classroom, array $updates): bool
    {
        $current = $this->snapshot($classroom);

        foreach (self::SIGNIFICANT_FIELDS as $field) {
            $value = $updates[$field] ?? null;
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }

            if (($current[$field] ?? null) !== $value) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function snapshot(Classroom $classroom): array
    {
        return [
            'title' => $classroom->title,
            'description' => $classroom->description,
            'purpose' => $classroom->purpose->value,
            'visibility' => $classroom->visibility->value,
            'max_members' => $classroom->max_members,
            'status' => $classroom->status->value,
        ];
    }
}
