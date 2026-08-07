<?php

declare(strict_types=1);

namespace Modules\Classroom\Actions;

use App\Support\Concerns\AsAction;
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
     *   linked_exam_id?: int|null,
     * }  $data
     */
    public function handle(Classroom $classroom, array $data): LiveSession
    {
        return LiveSession::create([
            'classroom_id' => $classroom->getKey(),
            'title' => $data['title'],
            'scheduled_at' => $data['scheduled_at'] ?? now(),
            'status' => LiveSessionStatus::Scheduled,
            'question_set' => $data['question_set'] ?? null,
            'linked_exam_id' => $data['linked_exam_id'] ?? null,
        ]);
    }
}
