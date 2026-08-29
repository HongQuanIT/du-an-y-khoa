@php
    use Modules\Classroom\Enums\LiveSessionStatus;

    $studioConfig = [
        'bootstrap_url' => route('teach.classes.sessions.studio.api.bootstrap', [$classroom, $session]),
        'classroom_uuid' => $classroom->uuid,
        'session_uuid' => $session->uuid,
        'status' => $session->status->value,
        'studio_mode' => true,
        'can_moderate' => true,
        'can_host_live' => true,
        'chat_readonly' => false,
        'chat_muted' => (bool) $session->chat_muted,
        'start_live_url' => $session->status === LiveSessionStatus::Scheduled
            ? route('teach.classes.sessions.start', [$classroom, $session])
            : null,
        'end_live_url' => route('teach.classes.sessions.end', [$classroom, $session]),
        'exit_url' => route('teach.classes.show', $classroom),
        'kick_member_url_template' => route('teach.classes.members.kick', [$classroom, '__USER__']),
        'has_questions' => $session->hasQuestionSet(),
        'user_id' => auth()->id(),
        'csrf' => csrf_token(),
    ];
@endphp

<x-layouts.live
    :title="$session->title"
    :classroom-title="$classroom->title"
    :session-title="'Live Studio - '.$session->title"
    :is-live="$session->status === LiveSessionStatus::Live"
    :exit-url="route('teach.classes.show', $classroom)"
>
    @if ($livekitConfigured)
        <div
            data-live-room
            data-live-config='@json($studioConfig)'
            data-lk-root
            data-lk-studio="1"
            @if ($session->hasQuestionSet() && $session->stage_teach)
                data-lk-stage-teach="1"
            @endif
            data-lk-url="{{ $tokenPayload['url'] }}"
            data-lk-token="{{ $tokenPayload['token'] }}"
            data-lk-role="{{ $tokenPayload['role'] }}"
            data-lk-room="{{ $tokenPayload['room'] }}"
            data-lk-can-audio="{{ ($tokenPayload['can_publish_audio'] ?? false) ? '1' : '0' }}"
            data-lk-can-video="{{ ($tokenPayload['can_publish_video'] ?? false) ? '1' : '0' }}"
            data-lk-can-screen="{{ ($tokenPayload['can_publish_screen'] ?? false) ? '1' : '0' }}"
            data-lk-exit-url="{{ route('teach.classes.show', $classroom) }}"
            class="flex min-h-0 flex-1 flex-col bg-black lg:flex-row"
        >
            <div class="flex min-h-0 flex-1 flex-col">
                <div data-lk-stage class="relative min-h-[240px] flex-1 overflow-hidden bg-black">
                    <div data-lk-main class="flex h-full w-full items-center justify-center">
                        <div class="text-center text-white/60">
                            <span class="material-symbols-outlined text-5xl">videocam</span>
                            <p class="mt-2 text-sm">Đang khởi tạo camera và micro...</p>
                        </div>
                    </div>
                    @include('classroom::live.partials.stage-teach')
                    <div data-live-reactions class="pointer-events-none absolute inset-0 z-30 overflow-hidden" aria-hidden="true"></div>
                    <div data-live-teach-banner
                        class="pointer-events-none absolute inset-x-0 top-0 z-20 hidden bg-teal-600/90 px-4 py-2 text-center text-xs text-white">
                        Chế độ chữa đề trên khung video — camera góc phải.
                    </div>
                </div>

                <div class="flex shrink-0 flex-col gap-3 border-t border-white/10 bg-black/90 px-3 py-3 sm:flex-row sm:items-center sm:justify-between md:px-5">
                    <div class="flex items-center gap-2 text-xs text-white/80">
                        <span data-lk-dot class="size-1.5 rounded-full bg-amber-400"></span>
                        <span data-lk-status>Đang kết nối...</span>
                        <span class="text-white/30">·</span>
                        <span data-lk-count class="text-white/60">1 người</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div data-lk-controls class="flex items-center gap-1">
                            <button type="button" data-lk-mic
                                class="inline-flex size-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 disabled:opacity-40"
                                aria-label="Micro" title="Bật hoặc tắt micro">
                                <span class="material-symbols-outlined text-[22px]">mic</span>
                            </button>
                            <button type="button" data-lk-cam
                                class="inline-flex size-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 disabled:opacity-40"
                                aria-label="Camera" title="Bật hoặc tắt camera">
                                <span class="material-symbols-outlined text-[22px]">videocam</span>
                            </button>
                            <button type="button" data-lk-screen
                                class="inline-flex size-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 disabled:opacity-40"
                                aria-label="Chia sẻ màn hình" title="Chia sẻ màn hình">
                                <span class="material-symbols-outlined text-[22px]">present_to_all</span>
                            </button>
                        </div>

                        <button type="button" data-lk-leave
                            class="inline-flex size-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                            aria-label="Rời Studio" title="Rời Studio nhưng không kết thúc live">
                            <span class="material-symbols-outlined text-[22px]">logout</span>
                        </button>

                        @if ($session->status === LiveSessionStatus::Scheduled)
                            <form method="post" action="{{ route('teach.classes.sessions.start', [$classroom, $session]) }}">
                                @csrf
                                <button type="submit"
                                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-error px-3 font-label-sm font-semibold text-white hover:opacity-90">
                                    <span class="material-symbols-outlined text-[20px]">radio_button_checked</span>
                                    Bắt đầu live
                                </button>
                            </form>
                        @elseif ($session->status === LiveSessionStatus::Live)
                            <form method="post" data-live-end-form action="{{ route('teach.classes.sessions.end', [$classroom, $session]) }}"
                                onsubmit="return confirm('Bạn chắc chắn muốn kết thúc buổi live? Học viên sẽ bị ngắt khỏi phòng.')">
                                @csrf
                                <button type="submit"
                                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-error px-3 font-label-sm font-semibold text-white hover:opacity-90">
                                    <span class="material-symbols-outlined text-[20px]">stop_circle</span>
                                    Kết thúc live
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <p data-lk-error class="hidden shrink-0 border-t border-red-400/30 bg-red-500/20 px-4 py-2 text-xs text-red-100"></p>
            </div>

            <aside class="flex h-[42vh] w-full shrink-0 flex-col overflow-hidden border-t border-white/10 bg-surface text-on-surface lg:h-auto lg:min-h-0 lg:w-[380px] lg:border-l lg:border-t-0 xl:w-[420px]">
                @include('classroom::live.partials.sidebar-tabs')
            </aside>
        </div>
    @else
        <div class="flex min-h-0 flex-1 items-center justify-center bg-black p-6">
            <div class="max-w-lg text-center text-white">
                <span class="material-symbols-outlined text-5xl text-red-300">cloud_off</span>
                <h2 class="mt-4 text-xl font-semibold">LiveKit chưa được cấu hình</h2>
                <p class="mt-2 text-sm text-white/60">Kiểm tra LIVEKIT_URL, LIVEKIT_API_KEY và LIVEKIT_API_SECRET rồi tải lại trang.</p>
            </div>
        </div>
    @endif
</x-layouts.live>
