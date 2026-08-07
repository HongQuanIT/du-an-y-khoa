<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
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

final class LiveMessageApiController extends Controller
{
    public function store(
        SendMessageRequest $request,
        Classroom $classroom,
        LiveSession $liveSession,
        SendLiveMessageAction $action,
    ): JsonResponse {
        $this->authorize('view', $classroom);
        abort_unless($classroom->isActiveMember($request->user()), 403);

        if ($liveSession->chat_muted && ! ($classroom->roleFor($request->user())?->canModerate() ?? false)) {
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
                'user' => [
                    'id' => $message->user?->getKey(),
                    'name' => $message->user?->name,
                ],
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

        return ApiResponse::item(['deleted' => true]);
    }
}
