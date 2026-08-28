<?php

declare(strict_types=1);

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Enums\Permission;
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
        abort_unless($classroom->canWatchLive($user), 403, 'Hãy tham gia lớp trước khi vào phòng live.');

        $observer = $request->routeIs('admin.*');
        if ($observer) {
            abort_unless($user->can(Permission::ClassroomOversee->value), 403);
        }

        $classroom->load('host');
        $liveSession->load([
            'messages' => fn ($q) => $q->where('is_hidden', false)->with('user')->latest('created_at')->limit(100),
        ]);
        $messages = $liveSession->messages->sortBy('created_at')->values();

        $role = ($observer || $classroom->purpose->isTeachPurpose())
            ? MemberRole::Member
            : ($classroom->roleFor($user) ?? MemberRole::Member);
        $publishSources = $observer
            ? []
            : ($classroom->purpose->isTeachPurpose() ? ['microphone'] : null);
        $tokenPayload = $liveSession->isLive()
            ? $tokens->issue($liveSession, $user, $role, $publishSources)
            : null;
        $canHostLive = ! $observer
            && ! $classroom->purpose->isTeachPurpose()
            && $user->can('manageLive', $classroom);
        $exitUrl = $observer
            ? route('admin.classrooms.index')
            : route('classroom.show', $classroom);
        $bootstrapUrl = $observer
            ? route('admin.classrooms.live.api.bootstrap', [$classroom, $liveSession])
            : route('classroom.live.api.bootstrap', [$classroom, $liveSession]);

        return view('classroom::live.room', [
            'classroom' => $classroom,
            'session' => $liveSession,
            'messages' => $messages,
            'role' => $role,
            'canModerate' => ! $observer && $role->canModerate(),
            'canHostLive' => $canHostLive,
            'tokenPayload' => $tokenPayload,
            'livekitConfigured' => $tokens->isConfigured(),
            'chatReadonly' => ! $liveSession->allowsChatSend(),
            'isObserver' => $observer,
            'exitUrl' => $exitUrl,
            'bootstrapUrl' => $bootstrapUrl,
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

        $redirectRoute = $request->routeIs('admin.*')
            ? route('admin.classrooms.live', [$classroom, $liveSession])
            : route('classroom.live', [$classroom, $liveSession]);

        return redirect()
            ->to($redirectRoute)
            ->withFragment('chat');
    }
}
