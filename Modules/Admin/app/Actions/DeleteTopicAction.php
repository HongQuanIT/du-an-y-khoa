<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Support\Auditor;
use Modules\QuestionBank\Models\Topic;

final class DeleteTopicAction
{
    use AsAction;

    public function handle(User $actor, Topic $topic): void
    {
        DB::transaction(function () use ($actor, $topic): void {
            $reasons = [];

            if ($topic->questions()->withTrashed()->exists() || $topic->questionsMany()->withTrashed()->exists()) {
                $reasons[] = 'đang được gắn với câu hỏi';
            }

            if (DB::table('topic_mastery')->where('topic_id', $topic->id)->exists()) {
                $reasons[] = 'đã có dữ liệu học tập';
            }

            if ($reasons !== []) {
                throw ValidationException::withMessages([
                    'topic' => 'Không thể xóa chủ đề vì '.implode(', ', $reasons).'.',
                ]);
            }

            $before = $topic->only(['name', 'slug', 'type', 'parent_id', 'order']);
            $topic->delete();

            Auditor::record('admin.topic.delete', $actor, $topic, $before, null);
        });
    }
}
