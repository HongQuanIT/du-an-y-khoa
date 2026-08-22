<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Admin\Actions\DeleteTopicAction;
use Modules\Admin\Actions\SaveTopicAction;
use Modules\Admin\Http\Requests\SaveTopicRequest;
use Modules\QuestionBank\Models\Topic;

final class TopicController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::TopicView);

        $query = Topic::query()
            ->withCount('questionsMany');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $topics = $query->orderBy('order')->orderBy('name')->get();

        return view('admin::topics.index', [
            'topics' => $topics,
            'stats' => [
                'total' => Topic::query()->count(),
                'used' => Topic::query()->whereHas('questionsMany')->count(),
                'unused' => Topic::query()->whereDoesntHave('questionsMany')->count(),
            ],
            'filters' => [
                'q' => $search,
            ],
            'canCreate' => $this->actor()->can(Permission::TopicCreate->value),
            'canUpdate' => $this->actor()->can(Permission::TopicUpdate->value),
            'canDelete' => $this->actor()->can(Permission::TopicDelete->value),
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission(Permission::TopicCreate);

        return view('admin::topics.form', $this->formData(new Topic([
            'type' => 'specialty',
            'order' => 0,
        ])));
    }

    public function store(SaveTopicRequest $request, SaveTopicAction $save): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicCreate);
        $topic = $save->handle($this->actor(), $request);

        return redirect()->route('admin.topics.edit', $topic)->with('status', 'Đã tạo chủ đề.');
    }

    public function edit(Topic $topic): View
    {
        $this->authorizePermission(Permission::TopicView);

        return view('admin::topics.form', $this->formData($topic));
    }

    public function update(SaveTopicRequest $request, Topic $topic, SaveTopicAction $save): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicUpdate);
        $save->handle($this->actor(), $request, $topic);

        return redirect()->route('admin.topics.edit', $topic)->with('status', 'Đã cập nhật chủ đề.');
    }

    public function destroy(Topic $topic, DeleteTopicAction $delete): RedirectResponse
    {
        $this->authorizePermission(Permission::TopicDelete);
        $delete->handle($this->actor(), $topic);

        return redirect()->route('admin.topics.index')->with('status', 'Đã xóa chủ đề.');
    }

    /** @return array<string, mixed> */
    private function formData(Topic $topic): array
    {
        return [
            'topic' => $topic,
            'canUpdate' => $topic->exists
                ? $this->actor()->can(Permission::TopicUpdate->value)
                : $this->actor()->can(Permission::TopicCreate->value),
            'canDelete' => $topic->exists && $this->actor()->can(Permission::TopicDelete->value),
        ];
    }

    private function authorizePermission(Permission $permission): void
    {
        abort_unless($this->actor()->can($permission->value), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
