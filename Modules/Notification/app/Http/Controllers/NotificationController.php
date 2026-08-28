<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Notification\Models\UserNotification;
use Modules\Notification\Support\NotificationCatalog;

final class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = (string) $request->query('filter', 'all');
        $category = (string) $request->query('category', '');

        $query = UserNotification::query()
            ->where('user_id', $user->getKey())
            ->latest();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        if ($category !== '' && in_array($category, NotificationCatalog::filterCategories(), true)) {
            $query->where('category', $category);
        }

        $notifications = $query->paginate(20)->withQueryString();

        $layout = $this->layoutFor($request);
        $indexRoute = $this->indexRouteName($request);
        $title = 'Thông báo';

        return view('notification::index', compact(
            'notifications',
            'filter',
            'category',
            'layout',
            'indexRoute',
            'title',
        ));
    }

    public function markRead(Request $request, UserNotification $notification): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->getKey(), 403);

        $notification->markRead();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'action_url' => $notification->action_url,
            ]);
        }

        if ($notification->action_url) {
            return redirect()->to($notification->action_url);
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    public function destroy(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->getKey(), 403);

        $notification->delete();

        return back();
    }

    private function layoutFor(Request $request): string
    {
        if ($request->routeIs('admin.*')) {
            return 'layouts.admin';
        }

        if ($request->routeIs('teach.*')) {
            return 'layouts.teach';
        }

        return 'layouts.app';
    }

    private function indexRouteName(Request $request): string
    {
        if ($request->routeIs('admin.*')) {
            return 'admin.notifications.index';
        }

        if ($request->routeIs('teach.*')) {
            return 'teach.notifications.index';
        }

        return 'notifications.index';
    }
}
