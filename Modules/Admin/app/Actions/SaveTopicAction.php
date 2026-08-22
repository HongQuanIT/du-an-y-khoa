<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Http\Requests\SaveTopicRequest;
use Modules\Admin\Support\Auditor;
use Modules\QuestionBank\Models\Topic;

final class SaveTopicAction
{
    use AsAction;

    public function handle(User $actor, SaveTopicRequest $request, ?Topic $topic = null): Topic
    {
        return DB::transaction(function () use ($actor, $request, $topic): Topic {
            $isNew = $topic === null;
            $topic ??= new Topic;
            $before = $isNew ? null : $topic->only(['name', 'slug', 'type', 'parent_id', 'order']);

            $topic->fill([
                'name' => $request->validated('name'),
                'slug' => $request->validated('slug'),
                'type' => $topic->type ?: 'specialty',
                'parent_id' => null,
                'order' => $request->integer('order'),
            ])->save();

            Auditor::record(
                $isNew ? 'admin.topic.create' : 'admin.topic.update',
                $actor,
                $topic,
                $before,
                $topic->only(['name', 'slug', 'type', 'parent_id', 'order']),
            );

            return $topic->refresh();
        });
    }
}
