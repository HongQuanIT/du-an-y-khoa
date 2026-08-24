<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Notification\Models\UserNotification;
use Modules\Notification\Support\NotificationCatalog;

final class NotificationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filter = (string) $request->query('filter', 'all');

        $query = UserNotification::query()
            ->where('user_id', $request->user()->getKey())
            ->latest();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        $items = $query->limit(50)->get()->map(fn (UserNotification $n) => [
            'id' => $n->id,
            'type' => $n->type,
            'category' => $n->category,
            'title' => $n->title,
            'body' => $n->body,
            'action_url' => $n->action_url,
            'icon' => NotificationCatalog::icon($n->type),
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $items]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->getKey(), 403);
        $notification->markRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, UserNotification $notification): Response
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->getKey(), 403);
        $notification->delete();

        return response()->noContent();
    }
}
