<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Notification\Actions\BroadcastSystemNotificationAction;

final class AdminBroadcastController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()?->can(Permission::SystemManage->value), 403);

        return view('notification::admin.broadcast', [
            'title' => 'Gửi thông báo hệ thống',
        ]);
    }

    public function store(Request $request, BroadcastSystemNotificationAction $action): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::SystemManage->value), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:2000'],
            'audience' => ['required', 'in:all,learners,instructors,staff'],
            'action_url' => ['nullable', 'string', 'max:500'],
            'type' => ['nullable', 'in:system.broadcast,system.maintenance'],
        ]);

        $count = $action->handle(
            actor: $request->user(),
            title: $data['title'],
            body: $data['body'],
            audience: $data['audience'],
            type: $data['type'] ?? 'system.broadcast',
            actionUrl: $data['action_url'] ?: null,
        );

        return redirect()
            ->route('admin.notifications.index')
            ->with('status', sprintf('Đã xếp hàng gửi tới %d người dùng.', $count));
    }
}
