<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Models\LiveSessionMessage;
use Modules\Classroom\Services\LiveKitTokenService;
use Modules\Classroom\Services\LiveQuestionPanelService;

final class LiveRoomApiController extends Controller
{
    public function bootstrap(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        LiveKitTokenService $tokens,
        LiveQuestionPanelService $questions,
    ): JsonResponse {
        $this->authorize('view', $classroom);
        abort_unless($classroom->isActiveMember($request->user()), 403);

        $liveSession->load([
            'messages' => fn ($q) => $q->where('is_hidden', false)->with('user')->orderBy('created_at')->limit(200),
            'recordings',
            'hands' => fn ($q) => $q->whereNull('acknowledged_at')->with('user'),
        ]);

        $role = $classroom->roleFor($request->user()) ?? MemberRole::Member;
        $tokenPayload = $liveSession->isLive()
            ? $tokens->issue($liveSession, $request->user(), $role)
            : null;

        $recording = $liveSession->recordings->first();

        return ApiResponse::item([
            'session' => [
                'uuid' => $liveSession->uuid,
                'title' => $liveSession->title,
                'status' => $liveSession->status->value,
                'chat_muted' => (bool) $liveSession->chat_muted,
                'chat_readonly' => ! $liveSession->allowsChatSend(),
            ],
            'classroom' => [
                'uuid' => $classroom->uuid,
                'title' => $classroom->title,
            ],
            'permissions' => [
                'can_publish' => $role->canPublish(),
                'can_moderate' => $role->canModerate(),
                'can_host_live' => $request->user()->can('manageLive', $classroom),
            ],
            'token' => $tokenPayload,
            'livekit_configured' => $tokens->isConfigured(),
            'messages' => $liveSession->messages->map(fn (LiveSessionMessage $m): array => $this->messagePayload($m))->values(),
            'hands' => $liveSession->hands->map(fn ($h): array => [
                'id' => $h->getKey(),
                'user' => ['id' => $h->user?->getKey(), 'name' => $h->user?->name],
                'raised_at' => $h->raised_at?->toIso8601String(),
            ])->values(),
            'question_panel' => $questions->panel($liveSession, $request->user()),
            'recording' => $recording ? [
                'status' => $recording->status->value,
                'playback_url' => $recording->status->value === 'ready'
                    ? route('classroom.live.recording', [$classroom, $liveSession])
                    : null,
            ] : null,
            'urls' => [
                'messages' => route('classroom.live.api.messages', [$classroom, $liveSession]),
                'token_refresh' => route('classroom.live.api.token', [$classroom, $liveSession]),
                'question' => route('classroom.live.api.question', [$classroom, $liveSession]),
                'raise_hand' => route('classroom.live.api.raise-hand', [$classroom, $liveSession]),
                'react' => route('classroom.live.api.react', [$classroom, $liveSession]),
                'mute_chat' => route('classroom.live.api.mute-chat', [$classroom, $liveSession]),
                'focus_questions' => route('classroom.live.api.focus-questions', [$classroom, $liveSession]),
                'presenter' => route('classroom.live.presenter', [$classroom, $liveSession]),
                'exit' => route('classroom.show', $classroom),
            ],
        ]);
    }

    public function refreshToken(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        LiveKitTokenService $tokens,
    ): JsonResponse {
        $this->authorize('view', $classroom);
        abort_unless($classroom->isActiveMember($request->user()), 403);
        abort_unless($liveSession->isLive(), 409, 'Session is not live.');

        $role = $classroom->roleFor($request->user()) ?? MemberRole::Member;

        return ApiResponse::item($tokens->issue($liveSession, $request->user(), $role));
    }

    /** @return array<string, mixed> */
    private function messagePayload(LiveSessionMessage $message): array
    {
        $message->loadMissing('user');

        return [
            'id' => $message->getKey(),
            'body' => $message->body,
            'type' => $message->type->value,
            'is_pinned' => $message->is_pinned,
            'created_at' => $message->created_at?->toIso8601String(),
            'user' => [
                'id' => $message->user?->getKey(),
                'name' => $message->user?->name,
            ],
        ];
    }
}
