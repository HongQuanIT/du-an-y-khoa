<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Enums\MemberStatus;
use Modules\Classroom\Events\LiveHandsUpdated;
use Modules\Classroom\Events\LiveReactionSent;
use Modules\Classroom\Events\LiveSessionUpdated;
use Modules\Classroom\Events\LiveSpeakerUpdated;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomMember;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Models\LiveSessionHand;
use Modules\Classroom\Support\LiveUserPresenter;

final class LiveModerationController extends Controller
{
    public function raiseHand(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
    ): JsonResponse {
        $this->authorize('view', $classroom);
        abort_unless($classroom->isActiveMember($request->user()), 403);
        abort_unless($liveSession->isLive(), 422);

        $existing = LiveSessionHand::query()
            ->where('live_session_id', $liveSession->getKey())
            ->where('user_id', $request->user()->getKey())
            ->whereNull('acknowledged_at')
            ->first();

        if ($existing !== null) {
            // Bấm lại = hạ tay.
            $existing->update(['acknowledged_at' => now()]);
            $hands = LiveHandsUpdated::serializeActiveHands($liveSession);
            event(new LiveHandsUpdated(
                $liveSession,
                $hands,
                'lowered',
                (int) $request->user()->getKey(),
            ));

            return ApiResponse::item([
                'hand_id' => $existing->getKey(),
                'raised' => false,
                'hands' => $hands,
            ]);
        }

        $hand = LiveSessionHand::query()->updateOrCreate(
            [
                'live_session_id' => $liveSession->getKey(),
                'user_id' => $request->user()->getKey(),
            ],
            [
                'raised_at' => now(),
                'acknowledged_at' => null,
            ],
        );

        $hands = LiveHandsUpdated::serializeActiveHands($liveSession);
        event(new LiveHandsUpdated(
            $liveSession,
            $hands,
            'raised',
            (int) $request->user()->getKey(),
        ));

        return ApiResponse::item([
            'hand_id' => $hand->getKey(),
            'raised' => true,
            'hands' => $hands,
        ]);
    }

    public function dismissHand(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        LiveSessionHand $hand,
    ): JsonResponse {
        $this->authorize('update', $classroom);
        abort_unless($hand->live_session_id === $liveSession->getKey(), 404);

        $actorId = (int) $hand->user_id;
        $hand->update(['acknowledged_at' => now()]);

        $hands = LiveHandsUpdated::serializeActiveHands($liveSession);
        event(new LiveHandsUpdated(
            $liveSession,
            $hands,
            'dismissed',
            $actorId,
        ));

        return ApiResponse::item([
            'dismissed' => true,
            'hands' => $hands,
        ]);
    }

    public function inviteSpeaker(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        User $user,
    ): JsonResponse {
        $this->authorize('update', $classroom);
        abort_unless($liveSession->isLive(), 422);
        abort_unless($classroom->isActiveMember($user), 404);
        abort_if($classroom->host_user_id === $user->getKey(), 422, 'Host đã có micro.');

        LiveSessionHand::query()
            ->where('live_session_id', $liveSession->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => now()]);

        $hands = LiveHandsUpdated::serializeActiveHands($liveSession);
        event(new LiveHandsUpdated(
            $liveSession,
            $hands,
            'dismissed',
            (int) $user->getKey(),
        ));

        event(new LiveSpeakerUpdated(
            $liveSession,
            'invite',
            (int) $user->getKey(),
            $request->user() !== null ? (int) $request->user()->getAuthIdentifier() : null,
        ));

        return ApiResponse::item([
            'invited' => true,
            'user_id' => (int) $user->getKey(),
            'hands' => $hands,
        ]);
    }

    public function muteSpeaker(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        User $user,
    ): JsonResponse {
        $this->authorize('update', $classroom);
        abort_unless($liveSession->isLive(), 422);
        abort_unless($classroom->isActiveMember($user), 404);

        event(new LiveSpeakerUpdated(
            $liveSession,
            'mute',
            (int) $user->getKey(),
            $request->user() !== null ? (int) $request->user()->getAuthIdentifier() : null,
        ));

        return ApiResponse::item([
            'muted' => true,
            'user_id' => (int) $user->getKey(),
        ]);
    }

    public function unmuteSpeaker(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        User $user,
    ): JsonResponse {
        $this->authorize('update', $classroom);
        abort_unless($liveSession->isLive(), 422);
        abort_unless($classroom->isActiveMember($user), 404);

        event(new LiveSpeakerUpdated(
            $liveSession,
            'unmute',
            (int) $user->getKey(),
            $request->user() !== null ? (int) $request->user()->getAuthIdentifier() : null,
        ));

        return ApiResponse::item([
            'unmuted' => true,
            'user_id' => (int) $user->getKey(),
        ]);
    }

    public function react(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
    ): JsonResponse {
        $this->authorize('view', $classroom);
        abort_unless($classroom->canWatchLive($request->user()), 403);
        abort_unless($liveSession->isLive(), 422);

        $type = $request->string('type')->toString();
        abort_unless(in_array($type, ['heart', 'like'], true), 422, 'Invalid reaction.');

        event(new LiveReactionSent($liveSession, $request->user(), $type));

        return ApiResponse::item([
            'type' => $type,
            'user' => LiveUserPresenter::toArray($request->user()),
        ]);
    }

    public function muteChat(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
    ): JsonResponse {
        $this->authorize('update', $classroom);

        $before = (bool) $liveSession->chat_muted;
        $liveSession->update(['chat_muted' => ! $liveSession->chat_muted]);
        event(new LiveSessionUpdated($liveSession, ['chat_muted' => $liveSession->chat_muted]));
        Auditor::record(
            AuditAction::ClassroomChatToggled,
            $request->user(),
            $liveSession,
            ['chat_muted' => $before],
            ['chat_muted' => (bool) $liveSession->chat_muted],
            metadata: ['classroom_id' => $classroom->getKey(), 'live_session_id' => $liveSession->getKey()],
            context: new AuditContext(sessionId: (string) $liveSession->getKey()),
        );

        return ApiResponse::item(['chat_muted' => $liveSession->chat_muted]);
    }

    public function banMember(
        Request $request,
        Classroom $classroom,
        User $user,
    ): JsonResponse {
        $this->authorize('update', $classroom);
        abort_if($classroom->host_user_id === $user->getKey(), 422, 'Cannot ban host.');

        ClassroomMember::query()
            ->where('classroom_id', $classroom->getKey())
            ->where('user_id', $user->getKey())
            ->update(['status' => MemberStatus::Banned->value]);

        $this->broadcastKick($classroom, $user);
        $this->auditMemberRemoval($request, $classroom, $user, true);

        return ApiResponse::item(['banned' => true]);
    }

    public function kickMember(
        Request $request,
        Classroom $classroom,
        User $user,
    ): JsonResponse {
        $this->authorize('update', $classroom);
        abort_if($classroom->host_user_id === $user->getKey(), 422, 'Cannot kick host.');
        abort_unless($classroom->isActiveMember($user), 404);

        $this->broadcastKick($classroom, $user);
        $this->auditMemberRemoval($request, $classroom, $user, false);

        return ApiResponse::item(['kicked' => true]);
    }

    private function broadcastKick(Classroom $classroom, User $user): void
    {
        $liveSession = $classroom->liveSession()->first();
        if ($liveSession !== null) {
            event(new LiveSessionUpdated($liveSession, [
                'kicked_user_id' => (int) $user->getKey(),
                'redirect_url' => route('classroom.index'),
            ]));
        }
    }

    private function auditMemberRemoval(Request $request, Classroom $classroom, User $target, bool $banned): void
    {
        $liveSession = $classroom->liveSession()->first();
        Auditor::record(
            AuditAction::ClassroomMemberKicked,
            $request->user(),
            $classroom,
            metadata: [
                'target_user_id' => $target->getKey(),
                'banned' => $banned,
                'live_session_id' => $liveSession?->getKey(),
            ],
            context: new AuditContext(sessionId: $liveSession !== null ? (string) $liveSession->getKey() : null),
        );
    }
}
