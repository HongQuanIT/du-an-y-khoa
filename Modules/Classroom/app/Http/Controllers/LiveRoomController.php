<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Actions\SendLiveMessageAction;
use Modules\Classroom\Enums\MemberRole;
use Modules\Classroom\Enums\MessageType;
use Modules\Classroom\Http\Requests\SendMessageRequest;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;
use Modules\Classroom\Services\LiveKitTokenService;

final class LiveRoomController extends Controller
{
    public function show(
        Request $request,
        Classroom $classroom,
        LiveSession $liveSession,
        LiveKitTokenService $tokens,
    ): View {
        $this->authorize('view', $classroom);

        $user = $request->user();
        abort_unless($classroom->isActiveMember($user), 403, 'Hãy tham gia lớp trước khi vào phòng live.');

        $classroom->load('host');
        $liveSession->load([
            'messages' => fn ($q) => $q->where('is_hidden', false)->with('user')->latest('created_at')->limit(100),
            'recordings',
        ]);
        $messages = $liveSession->messages->sortBy('created_at')->values();

        $role = $classroom->purpose->isTeachPurpose()
            ? MemberRole::Member
            : ($classroom->roleFor($user) ?? MemberRole::Member);
        $tokenPayload = $liveSession->isLive()
            ? $tokens->issue($liveSession, $user, $role, $classroom->purpose->isTeachPurpose() ? ['microphone'] : null)
            : null;
        $canHostLive = ! $classroom->purpose->isTeachPurpose() && $user->can('manageLive', $classroom);

        return view('classroom::live.room', [
            'classroom' => $classroom,
            'session' => $liveSession,
            'messages' => $messages,
            'role' => $role,
            'canModerate' => $role->canModerate(),
            'canHostLive' => $canHostLive,
            'tokenPayload' => $tokenPayload,
            'livekitConfigured' => $tokens->isConfigured(),
            'chatReadonly' => ! $liveSession->allowsChatSend(),
        ]);
    }

    public function message(
        SendMessageRequest $request,
        Classroom $classroom,
        LiveSession $liveSession,
        SendLiveMessageAction $action,
    ): RedirectResponse {
        $this->authorize('view', $classroom);

        $type = MessageType::tryFrom($request->string('type')->toString()) ?? MessageType::Chat;

        $action->handle($liveSession, $request->user(), $request->string('body')->toString(), $type);

        return redirect()
            ->route('classroom.live', [$classroom, $liveSession])
            ->withFragment('chat');
    }
}
