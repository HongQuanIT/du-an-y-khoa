<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Actions\SendLiveMessageAction;
use Modules\Classroom\Enums\MessageType;
use Modules\Classroom\Events\LiveMessageCreated;
use Modules\Classroom\Http\Requests\SendMessageRequest;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Models\LiveSessionMessage;
use Modules\Classroom\Support\LiveUserPresenter;

final class LiveMessageApiController extends Controller
{
    public function store(
        SendMessageRequest $request,
        Classroom $classroom,
        LiveSession $liveSession,
        SendLiveMessageAction $action,
    ): JsonResponse {
        $this->authorize('view', $classroom);
        abort_unless($classroom->canWatchLive($request->user()), 403);

        $isOverseer = $request->user()->can(\App\Support\Enums\Permission::ClassroomOversee->value);
        $canModerate = $isOverseer || ($classroom->roleFor($request->user())?->canModerate() ?? false);

        if ($liveSession->chat_muted && ! $canModerate) {
            return ApiResponse::error('CHAT_MUTED', 'Host đã tắt chat.', 422);
        }

        $type = MessageType::tryFrom($request->string('type')->toString()) ?? MessageType::Chat;

        $message = $action->handle(
            $liveSession,
            $request->user(),
            $request->string('body')->toString(),
            $type,
        );

        event(new LiveMessageCreated($liveSession, $message));

        $message->load('user');

        return ApiResponse::item([
            'message' => [
                'id' => $message->getKey(),
                'body' => $message->body,
                'type' => $message->type->value,
                'is_pinned' => $message->is_pinned,
                'created_at' => $message->created_at?->toIso8601String(),
                'user' => LiveUserPresenter::toArray($message->user),
            ],
        ], 201);
    }

    public function pin(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        LiveSessionMessage $message,
    ): JsonResponse {
        $this->authorize('update', $classroom);
        abort_unless($message->live_session_id === $liveSession->getKey(), 404);

        $message->update(['is_pinned' => ! $message->is_pinned]);
        $message->load('user');
        event(new LiveMessageCreated($liveSession, $message));

        return ApiResponse::item(['is_pinned' => $message->is_pinned]);
    }

    public function destroy(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        LiveSessionMessage $message,
    ): JsonResponse {
        $canDelete = $message->user_id === $request->user()->getKey()
            || ($classroom->roleFor($request->user())?->canModerate() ?? false);

        abort_unless($canDelete, 403);
        abort_unless($message->live_session_id === $liveSession->getKey(), 404);

        $message->update(['is_hidden' => true]);
        Auditor::record(
            AuditAction::ClassroomMessageDeleted,
            $request->user(),
            $message,
            ['is_hidden' => false],
            ['is_hidden' => true],
            metadata: [
                'classroom_id' => $classroom->getKey(),
                'live_session_id' => $liveSession->getKey(),
                'message_owner_id' => $message->user_id,
            ],
            context: new AuditContext(sessionId: (string) $liveSession->getKey()),
        );

        return ApiResponse::item(['deleted' => true]);
    }
}
