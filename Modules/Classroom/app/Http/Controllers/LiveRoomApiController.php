<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Enums\Permission;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Models\LiveSessionMessage;
use Modules\Classroom\Services\LiveKitTokenService;
use Modules\Classroom\Services\LiveQuestionPanelService;
use Modules\Classroom\Services\LiveTextMarksService;

final class LiveRoomApiController extends Controller
{
    public function bootstrap(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        LiveKitTokenService $tokens,
        LiveQuestionPanelService $questions,
        LiveTextMarksService $textMarks,
    ): JsonResponse {
        $this->authorize('view', $classroom);
        abort_unless($classroom->canWatchLive($request->user()), 403);

        $observer = $request->routeIs('admin.*');
        if ($observer) {
            abort_unless($request->user()->can(Permission::ClassroomOversee->value), 403);
        }

        $liveSession->load([
            'messages' => fn ($q) => $q->where('is_hidden', false)->with('user')->orderBy('created_at')->limit(200),
            'hands' => fn ($q) => $q->whereNull('acknowledged_at')->with('user'),
        ]);

        $teachPortal = $request->routeIs('teach.*');
        $role = $this->effectiveRole($request, $classroom, $teachPortal, $observer);
        $tokenPayload = $liveSession->isLive()
            ? $tokens->issue(
                $liveSession,
                $request->user(),
                $role,
                $this->publishSources($classroom, $teachPortal, $observer),
            )
            : null;

        $canHostLive = ! $observer && ($teachPortal || ! $classroom->purpose->isTeachPurpose())
            ? $request->user()->can('manageLive', $classroom)
            : false;
        $chatReadonly = ! $liveSession->allowsChatSend();
        $canModerate = ! $observer && $role->canModerate();

        return ApiResponse::item([
            'session' => [
                'uuid' => $liveSession->uuid,
                'title' => $liveSession->title,
                'status' => $liveSession->status->value,
                'chat_muted' => (bool) $liveSession->chat_muted,
                'chat_readonly' => $chatReadonly,
                'stage_teach' => (bool) $liveSession->stage_teach,
            ],
            'classroom' => [
                'uuid' => $classroom->uuid,
                'title' => $classroom->title,
            ],
            'permissions' => [
                'can_publish' => (bool) ($tokenPayload['can_publish_audio'] ?? false)
                    || (bool) ($tokenPayload['can_publish_video'] ?? false)
                    || (bool) ($tokenPayload['can_publish_screen'] ?? false),
                'can_publish_audio' => (bool) ($tokenPayload['can_publish_audio'] ?? false),
                'can_publish_video' => (bool) ($tokenPayload['can_publish_video'] ?? false),
                'can_publish_screen' => (bool) ($tokenPayload['can_publish_screen'] ?? false),
                'can_moderate' => $canModerate,
                'can_host_live' => $canHostLive,
            ],
            'token' => $tokenPayload,
            'livekit_configured' => $tokens->isConfigured(),
            'messages' => $liveSession->messages->map(fn (LiveSessionMessage $m): array => $this->messagePayload($m))->values(),
            'hands' => $liveSession->hands->map(fn ($h): array => [
                'id' => $h->getKey(),
                'user' => ['id' => $h->user?->getKey(), 'name' => $h->user?->name],
                'raised_at' => $h->raised_at?->toIso8601String(),
            ])->values(),
            'question_panel' => $questions->panel($liveSession),
            'question_deck' => $canModerate ? $questions->deck($liveSession) : null,
            'text_marks' => $textMarks->marks($liveSession),
            'urls' => $this->roomUrls($classroom, $liveSession, $teachPortal, $observer),
        ]);
    }

    public function refreshToken(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        LiveKitTokenService $tokens,
    ): JsonResponse {
        $this->authorize('view', $classroom);
        abort_unless($classroom->canWatchLive($request->user()), 403);
        abort_unless($liveSession->isLive(), 409, 'Session is not live.');

        $observer = $request->routeIs('admin.*');
        if ($observer) {
            abort_unless($request->user()->can(Permission::ClassroomOversee->value), 403);
        }

        $teachPortal = $request->routeIs('teach.*');
        $role = $this->effectiveRole($request, $classroom, $teachPortal, $observer);

        return ApiResponse::item($tokens->issue(
            $liveSession,
            $request->user(),
            $role,
            $this->publishSources($classroom, $teachPortal, $observer),
        ));
    }

    private function effectiveRole(Request $request, Classroom $classroom, bool $teachPortal, bool $observer = false): MemberRole
    {
        if ($observer || (! $teachPortal && $classroom->purpose->isTeachPurpose())) {
            return MemberRole::Member;
        }

        return $classroom->roleFor($request->user()) ?? MemberRole::Member;
    }

    /** @return list<string>|null */
    private function publishSources(Classroom $classroom, bool $teachPortal, bool $observer = false): ?array
    {
        if ($observer) {
            return [];
        }

        if (! $teachPortal && $classroom->purpose->isTeachPurpose()) {
            return ['microphone'];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function roomUrls(Classroom $classroom, LiveSession $liveSession, bool $teachPortal, bool $observer): array
    {
        if ($observer) {
            return [
                'messages' => route('admin.classrooms.live.api.messages', [$classroom, $liveSession]),
                'token_refresh' => route('admin.classrooms.live.api.token', [$classroom, $liveSession]),
                'question' => route('admin.classrooms.live.api.bootstrap', [$classroom, $liveSession]),
                'marks' => route('admin.classrooms.live.api.bootstrap', [$classroom, $liveSession]),
                'raise_hand' => route('admin.classrooms.live.api.bootstrap', [$classroom, $liveSession]),
                'react' => route('admin.classrooms.live.api.react', [$classroom, $liveSession]),
                'mute_chat' => route('admin.classrooms.live.api.bootstrap', [$classroom, $liveSession]),
                'focus_questions' => route('admin.classrooms.live.api.bootstrap', [$classroom, $liveSession]),
                'stage' => route('admin.classrooms.live.api.bootstrap', [$classroom, $liveSession]),
                'presenter' => route('admin.classrooms.live', [$classroom, $liveSession]),
                'exit' => route('admin.classrooms.index'),
            ];
        }

        return [
            'messages' => route($teachPortal ? 'teach.classes.sessions.studio.api.messages' : 'classroom.live.api.messages', [$classroom, $liveSession]),
            'token_refresh' => route($teachPortal ? 'teach.classes.sessions.studio.api.token' : 'classroom.live.api.token', [$classroom, $liveSession]),
            'question' => route($teachPortal ? 'teach.classes.sessions.studio.api.question' : 'classroom.live.api.question', [$classroom, $liveSession]),
            'marks' => route($teachPortal ? 'teach.classes.sessions.studio.api.marks' : 'classroom.live.api.marks', [$classroom, $liveSession]),
            'raise_hand' => route($teachPortal ? 'teach.classes.sessions.studio.api.raise-hand' : 'classroom.live.api.raise-hand', [$classroom, $liveSession]),
            'react' => route($teachPortal ? 'teach.classes.sessions.studio.api.react' : 'classroom.live.api.react', [$classroom, $liveSession]),
            'mute_chat' => route($teachPortal ? 'teach.classes.sessions.studio.api.mute-chat' : 'classroom.live.api.mute-chat', [$classroom, $liveSession]),
            'focus_questions' => route($teachPortal ? 'teach.classes.sessions.studio.api.focus-questions' : 'classroom.live.api.focus-questions', [$classroom, $liveSession]),
            'stage' => route($teachPortal ? 'teach.classes.sessions.studio.api.stage' : 'classroom.live.api.stage', [$classroom, $liveSession]),
            'presenter' => route($teachPortal ? 'teach.classes.sessions.studio.presenter' : 'classroom.live.presenter', [$classroom, $liveSession]),
            'exit' => route($teachPortal ? 'teach.classes.show' : 'classroom.show', $classroom),
        ];
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
