@php
    use Modules\Classroom\Enums\LiveSessionStatus;
    $bootstrapUrl = $bootstrapUrl ?? route('classroom.live.api.bootstrap', [$classroom, $session]);
    $exitUrl = $exitUrl ?? route('classroom.show', $classroom);
    $isObserver = $isObserver ?? false;
    $liveRoomConfig = [
        'bootstrap_url' => $bootstrapUrl,
        'classroom_uuid' => $classroom->uuid,
        'session_uuid' => $session->uuid,
        'status' => $session->status->value,
        'can_moderate' => $canModerate,
        'can_host_live' => $canHostLive,
        'chat_readonly' => $chatReadonly,
        'chat_muted' => (bool) $session->chat_muted,
        'end_live_url' => $canHostLive && $session->status === LiveSessionStatus::Live
            ? route('classroom.sessions.end', [$classroom, $session])
            : null,
        'start_live_url' => $canHostLive && $session->status === LiveSessionStatus::Scheduled
            ? route('classroom.sessions.start', [$classroom, $session])
            : null,
        'exit_url' => $exitUrl,
        'has_questions' => $session->hasQuestionSet(),
        'user_id' => auth()->id(),
        'csrf' => csrf_token(),
    ];
@endphp

<x-layouts.live
    :title="$session->title"
    :classroom-title="$classroom->title"
    :session-title="$session->title"
    :is-live="$session->status === LiveSessionStatus::Live"
    :exit-url="$exitUrl"
>
    <div
        data-live-room
        data-live-config='@json($liveRoomConfig)'
        @if ($livekitConfigured && $tokenPayload)
            data-lk-root
            data-lk-url="{{ $tokenPayload['url'] }}"
            data-lk-token="{{ $tokenPayload['token'] }}"
            data-lk-role="{{ $tokenPayload['role'] }}"
            data-lk-room="{{ $tokenPayload['room'] }}"
            data-lk-can-audio="{{ ($tokenPayload['can_publish_audio'] ?? false) ? '1' : '0' }}"
            data-lk-can-video="{{ ($tokenPayload['can_publish_video'] ?? false) ? '1' : '0' }}"
            data-lk-can-screen="{{ ($tokenPayload['can_publish_screen'] ?? false) ? '1' : '0' }}"
            data-lk-exit-url="{{ $exitUrl }}"
        @endif
        class="flex min-h-0 flex-1 flex-col lg:flex-row"
        x-data="{ mobileTab: 'video', sidebarTab: 'chat' }"
    >
        {{-- Stage column --}}
        <div class="relative flex min-h-0 flex-1 flex-col bg-black">
            @if ($session->status === LiveSessionStatus::Live && $livekitConfigured && $tokenPayload)
                <div data-lk-stage class="relative min-h-[220px] flex-1 overflow-hidden bg-black md:min-h-0">
                    <div data-live-reactions
                        class="pointer-events-none absolute inset-0 z-30 overflow-hidden"
                        aria-hidden="true"></div>
                    <div data-live-teach-banner
                        class="pointer-events-none absolute inset-x-0 top-0 z-20 hidden bg-teal-600/90 px-4 py-2 text-center text-xs text-white">
                        Chế độ chữa đề — học viên thấy tab <strong>Đề</strong> đồng bộ. Không cần share màn hình.
                    </div>
                    @if ($isObserver)
                        <div class="absolute inset-x-0 top-0 z-20 bg-amber-600/90 px-4 py-2 text-center text-xs text-white">
                            Đang xem với tư cách quản trị — {{ $classroom->host?->name ? 'Giảng viên: '.$classroom->host->name : 'không điều khiển buổi live' }}.
                        </div>
                    @endif
                    <div data-lk-waiting
                        class="{{ ($tokenPayload['role'] ?? '') === 'publisher' ? 'hidden' : 'flex' }} absolute inset-0 items-center justify-center bg-black/60 text-sm text-white/70">
                        Đang chờ host chia sẻ nội dung…
                    </div>
                </div>

                <div class="hidden shrink-0 items-center justify-between gap-2 border-t border-white/10 bg-black/80 px-3 py-2 md:flex">
                    <div class="flex items-center gap-2 text-xs text-white/80">
                        <span data-lk-dot class="size-1.5 rounded-full bg-amber-400"></span>
                        <span data-lk-status>Đang kết nối…</span>
                        <span data-lk-count class="text-white/60">1 người</span>
                    </div>
                    @include('classroom::live.partials.control-bar')
                </div>
                <p data-lk-error class="hidden border-t border-red-400/30 bg-red-500/20 px-4 py-2 text-xs text-red-100"></p>
            @elseif ($session->status === LiveSessionStatus::Ended)
                <div class="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
                    <div class="space-y-2">
                        <span class="material-symbols-outlined text-5xl text-white/60">replay</span>
                        <p class="text-lg font-semibold">Buổi live đã kết thúc</p>
                        <p class="text-sm text-white/60">Nội dung ghi hình không khả dụng.</p>
                    </div>
                </div>
            @elseif ($session->status === LiveSessionStatus::Scheduled)
                <div class="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
                    <span class="material-symbols-outlined text-5xl text-white/60">schedule</span>
                    <p class="text-lg font-semibold">Sẵn sàng bắt đầu</p>
                    @if ($session->hasQuestionSet())
                        <p class="text-sm text-white/60">{{ count($session->questionIds()) }} câu đã gắn cho buổi chữa đề.</p>
                    @endif
                    @if ($canHostLive)
                        <form method="post" action="{{ route('classroom.sessions.start', [$classroom, $session]) }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-error px-5 py-2.5 text-sm font-semibold text-white">
                                Bắt đầu live
                            </button>
                        </form>
                    @else
                        <p class="text-sm text-white/60">Host sẽ bắt đầu khi sẵn sàng.</p>
                    @endif
                </div>
            @else
                <div class="flex flex-1 items-center justify-center p-8 text-white/60">Phòng không khả dụng.</div>
            @endif

            {{-- Mobile control bar --}}
            @if ($session->status === LiveSessionStatus::Live && $livekitConfigured && $tokenPayload)
                <div class="flex shrink-0 items-center justify-center border-t border-white/10 bg-black/90 px-2 py-2 md:hidden">
                    @include('classroom::live.partials.control-bar')
                </div>
            @endif
        </div>

        {{-- Sidebar (desktop) — text-on-surface resets body text-white from live layout --}}
        <aside class="hidden w-full max-w-md shrink-0 flex-col overflow-hidden border-l border-white/10 bg-surface text-on-surface lg:flex lg:min-h-0 lg:w-[380px] xl:w-[420px]">
            @include('classroom::live.partials.sidebar-tabs')
        </aside>

        {{-- Mobile bottom tabs --}}
        <div class="flex shrink-0 flex-col border-t border-outline-variant bg-surface text-on-surface lg:hidden">
            <div class="flex border-b border-outline-variant">
                <button type="button" @click="mobileTab = 'video'"
                    :class="mobileTab === 'video' ? 'border-b-2 border-primary text-primary' : 'text-on-surface-variant'"
                    class="flex-1 py-2.5 text-sm font-medium">Video</button>
                <button type="button" @click="mobileTab = 'chat'"
                    :class="mobileTab === 'chat' ? 'border-b-2 border-primary text-primary' : 'text-on-surface-variant'"
                    class="flex-1 py-2.5 text-sm font-medium">Chat</button>
            @if ($session->hasQuestionSet())
                <button type="button" data-live-tab="questions" @click="mobileTab = 'questions'"
                    :class="mobileTab === 'questions' ? 'border-b-2 border-primary text-primary' : 'text-on-surface-variant'"
                    class="flex-1 py-2.5 text-sm font-medium">Đề</button>
            @endif
            </div>
            <div x-show="mobileTab !== 'video'" class="h-[45vh] overflow-hidden">
                @include('classroom::live.partials.sidebar-tabs', ['mobile' => true])
            </div>
        </div>
    </div>
</x-layouts.live>
