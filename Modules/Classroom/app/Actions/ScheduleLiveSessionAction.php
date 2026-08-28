<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Support\Concerns\AsAction;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Enums\LiveSessionStatus;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;

final class ScheduleLiveSessionAction
{
    use AsAction;

    /**
     * @param  array{
     *   title: string,
     *   scheduled_at?: string|null,
     *   question_set?: array<string, mixed>|null,
     *   linked_exam_id?: int|null, expected_duration_seconds?: int|null,
     * }  $data
     */
    public function handle(Classroom $classroom, array $data): LiveSession
    {
        $scheduledAt = isset($data['scheduled_at']) && $data['scheduled_at'] !== null
            ? CarbonImmutable::parse($data['scheduled_at'])
            : now()->toImmutable();
        $duration = max(900, (int) ($data['expected_duration_seconds'] ?? 3600));

        $this->ensureHostIsAvailable($classroom, $scheduledAt, $duration);

        return LiveSession::create([
            'classroom_id' => $classroom->getKey(),
            'title' => $data['title'],
            'scheduled_at' => $scheduledAt,
            'expected_duration_seconds' => $duration,
            'status' => LiveSessionStatus::Scheduled,
            'question_set' => $data['question_set'] ?? null,
            'linked_exam_id' => $data['linked_exam_id'] ?? null,
        ]);
    }

    private function ensureHostIsAvailable(Classroom $classroom, CarbonImmutable $startsAt, int $duration): void
    {
        $endsAt = $startsAt->addSeconds($duration);

        $conflicts = LiveSession::query()
            ->whereIn('status', [
                LiveSessionStatus::Scheduled->value,
                LiveSessionStatus::Starting->value,
                LiveSessionStatus::Live->value,
            ])
            ->whereHas('classroom', fn ($query) => $query->where('host_user_id', $classroom->host_user_id))
            ->whereNotNull('scheduled_at')
            ->get(['scheduled_at', 'expected_duration_seconds']);

        $hasConflict = $conflicts->contains(function (LiveSession $session) use ($startsAt, $endsAt): bool {
            $otherStart = $session->scheduled_at?->toImmutable();
            if ($otherStart === null) {
                return false;
            }

            $otherEnd = $otherStart->addSeconds(max(900, (int) ($session->expected_duration_seconds ?? 3600)));

            return $startsAt < $otherEnd && $endsAt > $otherStart;
        });

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Giảng viên chủ lớp đã có buổi live trùng thời gian dự kiến.',
            ]);
        }
    }
}
