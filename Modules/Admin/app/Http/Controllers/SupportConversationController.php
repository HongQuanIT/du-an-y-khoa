<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Events\SupportMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Support\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SupportConversationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAccess();
        $admin = $request->user();
        $query = SupportConversation::query()
            ->with([
                'user',
                'assignedAdmin',
                'latestMessage',
                'adminReads' => fn ($q) => $q->where('admin_id', $admin->id),
            ])
            ->latest('last_message_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($request->query('needs_reply') === '1') {
            $query->where(function ($q): void {
                $q->where('status', 'waiting_admin')
                    ->orWhere(function ($inner): void {
                        $inner->where('status', 'admin_active')
                            ->whereHas('latestMessage', fn ($m) => $m->where('sender_type', 'user'));
                    });
            });
        }

        $conversations = $query->paginate(20)->withQueryString();

        return view('admin::support.index', [
            'conversations' => $conversations,
            'admin' => $admin,
        ]);
    }

    public function badge(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        return response()->json([
            'count' => SupportConversation::pendingAdminAttentionCountFor($request->user()),
        ]);
    }

    public function seen(Request $request, SupportConversation $conversation): JsonResponse
    {
        $this->authorizeAccess();
        $conversation->markSeenByAdmin($request->user());

        return response()->json([
            'count' => SupportConversation::pendingAdminAttentionCountFor($request->user()),
        ]);
    }

    public function claim(Request $request, SupportConversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess();
        abort_if($conversation->status === 'resolved', 422, 'Cuộc trò chuyện đã được đóng.');

        $conversation->claimByAdmin($request->user());
        $conversation->markSeenByAdmin($request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'redirect' => route('admin.support.show', $conversation),
            ]);
        }

        return redirect()->route('admin.support.show', $conversation);
    }

    public function show(Request $request, SupportConversation $conversation): View
    {
        $this->authorizeAccess();
        $admin = $request->user();
        $conversation->load(['user', 'assignedAdmin', 'messages.sender']);

        $requiresTakeoverConfirm = $conversation->isHandledByOtherAdmin($admin);

        if (! $requiresTakeoverConfirm) {
            if ($conversation->assigned_admin_id === null
                && in_array($conversation->status, ['waiting_admin', 'admin_active'], true)) {
                $conversation->claimByAdmin($admin);
                $conversation->load('assignedAdmin');
            }
            $conversation->markSeenByAdmin($admin);
        }

        return view('admin::support.show', [
            'conversation' => $conversation,
            'admin' => $admin,
            'requiresTakeoverConfirm' => $requiresTakeoverConfirm,
        ]);
    }

    public function message(Request $request, SupportConversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess();
        abort_if($conversation->status === 'resolved', 422, 'Cuộc trò chuyện đã được đóng.');
        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'sender_type' => 'admin',
            'body' => trim($data['message']),
        ]);
        $conversation->forceFill([
            'assigned_admin_id' => $request->user()->id,
            'status' => 'admin_active',
            'last_message_at' => now(),
        ])->save();
        $conversation->markSeenByAdmin($request->user());
        SupportMessageCreated::dispatch($message);

        if ($request->expectsJson()) {
            $updatedConversation = SupportConversation::query()->findOrFail($conversation->id)->load(['user', 'messages']);

            return response()->json(['conversation' => [
                'id' => $updatedConversation->id,
                'status' => $updatedConversation->status,
                'messages' => $updatedConversation->messages->map(fn (SupportMessage $item) => [
                    'id' => $item->id,
                    'sender_type' => $item->sender_type,
                    'sender_id' => $item->sender_id,
                    'body' => $item->body,
                    'created_at' => $item->created_at?->toIso8601String(),
                ])->values(),
            ]]);
        }

        return back();
    }

    public function resolve(SupportConversation $conversation): RedirectResponse
    {
        $this->authorizeAccess();
        $conversation->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save();

        return redirect()->route('admin.support.index')->with('status', 'Đã đóng yêu cầu hỗ trợ.');
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can(Permission::SystemManage->value), 403);
    }
}
