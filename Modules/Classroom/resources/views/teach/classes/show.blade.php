@php
    use Modules\Classroom\Enums\ClassroomStatus;
    use Modules\Classroom\Enums\LiveSessionStatus;

    $isClosed = $classroom->status === ClassroomStatus::Closed;
    $canClose = $canCloseClassroom;
@endphp

<x-layouts.teach
    :title="$classroom->title"
    :description="'Quản lý lớp ' . $classroom->title . ', lịch live, câu hỏi và thành viên trên MedLearn.'">
    <div class="mx-auto max-w-7xl">
        <nav aria-label="Điều hướng lớp học" class="text-sm text-on-surface-variant">
            <ol class="flex flex-wrap items-center gap-2">
                <li><a href="{{ route('teach.dashboard') }}" class="hover:text-primary hover:underline">Tổng quan</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('teach.classes.index') }}" class="hover:text-primary hover:underline">Lớp của tôi</a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page" class="max-w-64 truncate font-semibold text-on-surface">{{ $classroom->title }}</li>
            </ol>
        </nav>

        @if (session('status'))
            <div role="status" class="mt-5 flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-primary">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">check_circle</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <header class="mt-5 rounded-xl border border-outline-variant bg-surface p-5 md:p-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="mb-3 flex flex-wrap items-center gap-2" aria-label="Trạng thái lớp học">
                        @if ($classroom->liveSession)
                            <span class="inline-flex items-center gap-1 rounded-full bg-error/10 px-2.5 py-1 text-xs font-semibold text-error">
                                <span class="size-2 rounded-full bg-error" aria-hidden="true"></span>Đang live
                            </span>
                        @endif
                        <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                            {{ $classroom->status->label() }}
                        </span>
                        <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs text-primary">
                            {{ $classroom->purpose->label() }}
                        </span>
                        <span class="rounded-full bg-surface-container-high px-2.5 py-1 text-xs text-on-surface-variant">
                            {{ $classroom->visibility->label() }}
                        </span>
                    </div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-primary">Chi tiết lớp học</p>
                    <h1 class="mt-1 break-words font-headline-md text-headline-md font-bold text-on-surface">{{ $classroom->title }}</h1>
                    @if ($classroom->description)
                        <p class="mt-3 max-w-3xl whitespace-pre-line text-sm leading-6 text-on-surface-variant">{{ $classroom->description }}</p>
                    @else
                        <p class="mt-3 text-sm italic text-on-surface-variant">Lớp chưa có mô tả.</p>
                    @endif
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a href="{{ route('teach.classes.edit', $classroom) }}"
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-outline-variant px-4 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                        Chỉnh sửa
                    </a>
                    @if ($canClose)
                        <form method="post" action="{{ route('teach.classes.close', $classroom) }}"
                            onsubmit="return confirm('Đóng lớp này? Trạng thái sẽ chuyển sang Đã đóng và học viên không thể tham gia hoặc vào buổi live mới.')">
                            @csrf
                            <button type="submit"
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-primary px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/5">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">lock</span>
                                Đóng lớp
                            </button>
                        </form>
                    @endif
                    @if ($isClosed)
                        <form method="post" action="{{ route('teach.classes.reopen', $classroom) }}"
                            onsubmit="return confirm('Mở lại lớp này? Phê duyệt trước đó sẽ được giữ nguyên và học viên có thể truy cập lại lớp.')">
                            @csrf
                            <button type="submit"
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-on-primary hover:opacity-90">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">lock_open</span>
                                Mở lại lớp
                            </button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('teach.classes.destroy', $classroom) }}"
                        onsubmit="return confirm('Xoá lớp này? Hành động này sẽ ẩn lớp khỏi portal giảng viên và học viên.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-error px-4 py-2 text-sm font-semibold text-error hover:bg-error/5">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                            Xoá lớp
                        </button>
                    </form>
                </div>
            </div>

            <dl class="mt-6 grid gap-3 border-t border-outline-variant pt-5 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg bg-surface-container-low px-4 py-3">
                    <dt class="text-xs text-on-surface-variant">Giảng viên chủ trì</dt>
                    <dd class="mt-1 truncate font-semibold text-on-surface">{{ $classroom->host?->name ?? 'Chưa xác định' }}</dd>
                </div>
                <div class="rounded-lg bg-surface-container-low px-4 py-3">
                    <dt class="text-xs text-on-surface-variant">Thành viên</dt>
                    <dd class="mt-1 font-semibold text-on-surface">
                        {{ $classroom->active_members_count }}{{ $classroom->max_members ? ' / '.$classroom->max_members : ' · không giới hạn' }}
                    </dd>
                </div>
                <div class="rounded-lg bg-surface-container-low px-4 py-3">
                    <dt class="text-xs text-on-surface-variant">Mã tham gia</dt>
                    <dd class="mt-1 font-mono font-semibold tracking-wide text-on-surface">{{ $classroom->join_code ?? 'Chưa có' }}</dd>
                </div>
                <div class="rounded-lg bg-surface-container-low px-4 py-3">
                    <dt class="text-xs text-on-surface-variant">Ngày tạo</dt>
                    <dd class="mt-1 font-semibold text-on-surface">{{ $classroom->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
                <div class="rounded-lg bg-surface-container-low px-4 py-3">
                    <dt class="text-xs text-on-surface-variant">Mã hệ thống</dt>
                    <dd class="mt-1 truncate font-mono text-xs font-semibold text-on-surface" title="{{ $classroom->uuid }}">{{ $classroom->uuid }}</dd>
                </div>
            </dl>
        </header>

        @if ($classroom->status === \Modules\Classroom\Enums\ClassroomStatus::PendingApproval)
            <section aria-labelledby="approval-status-title" class="mt-6 flex items-start gap-3 rounded-xl border border-tertiary/30 bg-tertiary/10 px-5 py-4 text-on-surface">
                <span class="material-symbols-outlined text-tertiary" aria-hidden="true">pending_actions</span>
                <div>
                    <h2 id="approval-status-title" class="font-semibold">Lớp đang chờ admin phê duyệt</h2>
                    <p class="mt-1 text-sm leading-5 text-on-surface-variant">
                        Bạn có thể chọn câu hỏi, lên lịch và mở Studio để kiểm tra thiết bị. Học viên chỉ nhìn thấy hoặc tham gia lớp sau khi lớp đạt trạng thái phù hợp.
                    </p>
                </div>
            </section>
        @endif

        @if ($isClosed)
            <section aria-labelledby="closed-status-title" class="mt-6 flex items-start gap-3 rounded-xl border border-outline-variant bg-surface-container-low px-5 py-4">
                <span class="material-symbols-outlined text-on-surface-variant" aria-hidden="true">lock</span>
                <div>
                    <h2 id="closed-status-title" class="font-semibold text-on-surface">Lớp đã đóng</h2>
                    <p class="mt-1 text-sm leading-5 text-on-surface-variant">
                        Lớp không còn nhận học viên và không thể lên lịch hoặc bắt đầu buổi live. Lịch sử vẫn được giữ; bạn có thể mở lại lớp mà không cần duyệt lại nếu nội dung không thay đổi đáng kể.
                    </p>
                </div>
            </section>
        @endif

        <div class="mt-6 grid items-start gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
            <section id="live-management" aria-labelledby="live-management-title" class="scroll-mt-24 rounded-xl border border-outline-variant bg-surface p-5 md:p-6">
                <div class="mb-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary">Nội dung giảng dạy</p>
                    <h2 id="live-management-title" class="mt-1 font-title-lg text-title-lg font-bold text-on-surface">Quản lý buổi live</h2>
                    <p class="mt-1 text-sm leading-5 text-on-surface-variant">
                        Lên lịch, chọn tối đa 50 câu hỏi đã xuất bản và quản lý quá trình phát trực tiếp của lớp.
                    </p>
                </div>

                @if (! $isClosed && ! $classroom->liveSession)
                    <div x-data="classroomQuestionPicker(@js(route('teach.classes.questions.search', $classroom)))" x-init="loadQuestions()"
                        class="mb-5 rounded-lg border border-outline-variant bg-surface-container-lowest p-4 md:p-5">
                        <form method="post" action="{{ route('teach.classes.sessions.store', $classroom) }}" class="space-y-5">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px_160px]">
                                <label for="session-title">
                                    <span class="mb-1 block text-sm font-medium text-on-surface">Tiêu đề buổi live <span class="text-error">*</span></span>
                                    <input id="session-title" name="title" value="{{ old('title') }}" required maxlength="200"
                                        placeholder="Ví dụ: Chữa đề Nội khoa"
                                        class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 font-body-sm text-on-surface">
                                </label>
                                <label for="scheduled-at">
                                    <span class="mb-1 block text-sm font-medium text-on-surface">Thời gian dự kiến</span>
                                    <input id="scheduled-at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"
                                        class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 font-body-sm text-on-surface">
                                </label>
                                <label for="expected-duration">
                                    <span class="mb-1 block text-sm font-medium text-on-surface">Thời lượng (phút)</span>
                                    <input id="expected-duration" type="number" name="expected_duration_minutes" min="15" max="480" step="15" value="{{ old('expected_duration_minutes', 60) }}"
                                        class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 font-body-sm text-on-surface">
                                </label>
                            </div>
                            <p class="text-xs text-on-surface-variant">Hệ thống kiểm tra lịch trùng của giảng viên chủ lớp theo thời gian và thời lượng dự kiến.</p>

                            <div class="grid min-h-[320px] gap-4 lg:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <h3 class="font-label-md font-semibold text-on-surface">Câu đã chọn</h3>
                                        <span class="font-label-sm text-on-surface-variant" x-text="selected.length + '/50 câu'">0/50 câu</span>
                                    </div>
                                    <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
                                        <template x-for="(question, index) in selected" :key="question.id">
                                            <div class="flex items-start gap-3 rounded-lg bg-surface-container-lowest p-3">
                                                <input type="hidden" name="question_ids[]" :value="question.id">
                                                <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/10 font-label-sm text-primary" x-text="index + 1"></span>
                                                <div class="min-w-0 flex-1">
                                                    <p class="line-clamp-2 font-body-sm text-on-surface" x-text="question.stem"></p>
                                                    <p class="mt-1 font-label-sm text-on-surface-variant" x-text="question.topic + ' · ' + question.difficulty"></p>
                                                </div>
                                                <button type="button" @click="remove(question.id)" class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-error hover:bg-error/10" aria-label="Bỏ câu hỏi">
                                                    <span class="material-symbols-outlined text-[19px]">close</span>
                                                </button>
                                            </div>
                                        </template>
                                        <div x-show="selected.length === 0" class="rounded-lg border border-dashed border-outline-variant px-4 py-10 text-center">
                                            <span class="material-symbols-outlined text-3xl text-on-surface-variant">playlist_add</span>
                                            <p class="mt-2 font-body-sm text-on-surface-variant">Chưa chọn câu hỏi. Buổi live vẫn có thể tạo không kèm đề.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="min-w-0 border-t border-outline-variant pt-4 lg:border-l lg:border-t-0 lg:pl-4 lg:pt-0">
                                    <label for="question-search" class="mb-2 block text-sm font-semibold text-on-surface">Thư viện câu hỏi</label>
                                    <div class="relative block">
                                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-on-surface-variant">search</span>
                                        <input id="question-search" type="search" x-model="search" @input="scheduleSearch()" placeholder="Tìm theo nội dung câu hỏi..."
                                            class="w-full rounded-lg border border-outline-variant bg-surface py-2 pl-10 pr-3 font-body-sm text-on-surface">
                                    </div>
                                    <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
                                        <template x-for="question in availableQuestions" :key="question.id">
                                            <button type="button" @click="add(question)" :disabled="selected.some(item => item.id === question.id) || selected.length >= 50"
                                                class="block w-full rounded-lg border border-outline-variant bg-surface p-3 text-left hover:border-primary hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-45">
                                                <p class="line-clamp-2 font-body-sm text-on-surface" x-text="question.stem"></p>
                                                <p class="mt-1 font-label-sm text-on-surface-variant" x-text="question.topic + ' · ' + question.difficulty"></p>
                                            </button>
                                        </template>
                                        <p x-show="loading" class="py-8 text-center font-body-sm text-on-surface-variant">Đang tìm câu hỏi...</p>
                                        <p x-show="!loading && availableQuestions.length === 0" class="py-8 text-center font-body-sm text-on-surface-variant">Không tìm thấy câu hỏi phù hợp.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 border-t border-outline-variant pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-xs text-on-surface-variant">Bạn có thể lên lịch mà không chọn câu hỏi và bổ sung nội dung sau.</p>
                                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary hover:opacity-90">
                                    <span class="material-symbols-outlined text-[20px]">event</span>
                                    Lên lịch buổi live
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-error/20 bg-error/5 px-3 py-2 font-body-sm text-error">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if ($classroom->liveSession)
                    <div class="mb-5 rounded-lg border border-error/25 bg-error/5 p-4">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-error">Đang phát trực tiếp</p>
                                <h3 class="mt-1 font-semibold text-on-surface">{{ $classroom->liveSession->title }}</h3>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('teach.classes.sessions.studio', [$classroom, $classroom->liveSession]) }}"
                                    class="inline-flex items-center gap-2 rounded-lg bg-error px-3 py-2 text-sm font-semibold text-on-error hover:opacity-90">
                                    <span class="material-symbols-outlined text-[18px]">videocam</span>Vào Live Studio
                                </a>
                                <form method="post" action="{{ route('teach.classes.sessions.end', [$classroom, $classroom->liveSession]) }}"
                                    onsubmit="return confirm('Bạn chắc chắn muốn kết thúc buổi live? Học viên sẽ bị ngắt khỏi phòng. Bạn vẫn có thể mở lại buổi này từ trang lớp nếu kết thúc nhầm.')">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-error px-3 py-2 text-sm font-semibold text-error hover:bg-error/5">Kết thúc live</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($upcomingSessions->isNotEmpty())
                    <section aria-labelledby="upcoming-sessions-title">
                        <h3 id="upcoming-sessions-title" class="mb-3 font-semibold text-on-surface">Lịch sắp tới</h3>
                    <ul class="mb-4 space-y-2">
                        @foreach ($upcomingSessions as $session)
                            <li class="flex items-center justify-between rounded-lg bg-surface-container-lowest px-3 py-2.5">
                                <div>
                                    <p class="font-label-md text-on-surface">{{ $session->title }}</p>
                                    <p class="font-body-sm text-on-surface-variant">
                                        {{ $session->scheduled_at?->format('d/m/Y H:i') ?? 'Chưa đặt giờ' }}
                                        · {{ $session->status->label() }}
                                        @if ($session->expected_duration_seconds)
                                            · {{ (int) ceil($session->expected_duration_seconds / 60) }} phút
                                        @endif
                                        @if ($session->hasQuestionSet())
                                            · {{ count($session->questionIds()) }} câu hỏi
                                        @endif
                                    </p>
                                </div>
                                @if (! $isClosed && $session->status === LiveSessionStatus::Scheduled)
                                    <form method="post" action="{{ route('teach.classes.sessions.start', [$classroom, $session]) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-primary px-3 py-1.5 font-label-sm font-semibold text-on-primary hover:opacity-90">Bắt đầu</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    </section>
                @else
                    <div class="rounded-lg border border-dashed border-outline-variant bg-surface-container-lowest px-4 py-8 text-center">
                        <span class="material-symbols-outlined text-[32px] text-on-surface-variant">event</span>
                        <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">Chưa có lịch live.</p>
                    </div>
                @endif

                @if ($pastSessions->isNotEmpty())
                    <div class="mt-6 border-t border-outline-variant pt-4">
                        <h3 class="mb-2 font-label-sm uppercase tracking-wide text-on-surface-variant">Lịch sử buổi live</h3>
                        <ul class="space-y-2">
                            @foreach ($pastSessions as $session)
                                <li class="flex flex-col gap-2 rounded-lg bg-surface-container-lowest px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="text-sm text-on-surface-variant">
                                        <span class="font-semibold text-on-surface">{{ $session->title }}</span>
                                        · {{ $session->status->label() }}
                                        @if ($session->ended_at)
                                            · {{ $session->ended_at->format('d/m/Y H:i') }}
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @if (! $isClosed && $session->status === LiveSessionStatus::Ended)
                                            <form method="post" action="{{ route('teach.classes.sessions.start', [$classroom, $session]) }}"
                                                onsubmit="return confirm('Mở lại buổi live này? Học viên có thể vào lại phòng và nội dung câu hỏi hiện tại sẽ được giữ nguyên.')">
                                                @csrf
                                                <button type="submit"
                                                    class="inline-flex items-center justify-center gap-1 rounded-lg border border-primary px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/5">
                                                    <span class="material-symbols-outlined text-[17px]" aria-hidden="true">restart_alt</span>
                                                    Mở lại buổi live
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </div>

            <aside aria-label="Thông tin lớp và thành viên" class="space-y-6 lg:sticky lg:top-24">
            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <h2 class="font-title-md font-semibold text-on-surface">Thông tin vận hành</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-on-surface-variant">Trạng thái</dt><dd class="text-right font-semibold text-on-surface">{{ $classroom->status->label() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-on-surface-variant">Loại lớp</dt><dd class="text-right text-on-surface">{{ $classroom->purpose->label() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-on-surface-variant">Quyền tham gia</dt><dd class="text-right text-on-surface">{{ $classroom->visibility->label() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-on-surface-variant">Mã lớp</dt><dd class="font-mono font-semibold text-on-surface">{{ $classroom->join_code ?? '—' }}</dd></div>
                </dl>
                <p class="mt-4 rounded-lg bg-surface-container-low p-3 text-xs leading-5 text-on-surface-variant">
                    Chỉ chia sẻ mã lớp với đúng nhóm học viên. Khả năng tham gia còn phụ thuộc trạng thái duyệt và chế độ của lớp.
                </p>
            </section>

            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <h2 class="font-title-md font-semibold text-on-surface">Thành viên</h2>
                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                    {{ $classroom->active_members_count }} đang tham gia
                    @if ($classroom->max_members)
                        / tối đa {{ $classroom->max_members }}
                    @endif
                </p>
                <ul class="mt-4 max-h-64 space-y-2 overflow-y-auto">
                    @forelse ($members as $member)
                        <li class="flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 text-sm hover:bg-surface-container-low">
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">{{ mb_strtoupper(mb_substr($member->user?->name ?? '?', 0, 1)) }}</span>
                                <span class="truncate text-on-surface">{{ $member->user?->name ?? '—' }}</span>
                            </span>
                            <span class="shrink-0 font-label-sm text-on-surface-variant">{{ $member->role_in_class->label() }}</span>
                        </li>
                    @empty
                        <li class="font-body-sm text-on-surface-variant">Chưa có thành viên.</li>
                    @endforelse
                </ul>
                @if ($classroom->active_members_count > $members->count())
                    <p class="mt-3 font-body-sm text-on-surface-variant">Đang hiển thị 50 thành viên mới nhất.</p>
                @endif
            </section>

            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <h2 class="font-title-md font-semibold text-on-surface">Bước tiếp theo</h2>
                <ol class="mt-3 space-y-3 text-sm text-on-surface-variant">
                    <li class="flex gap-2"><span class="font-semibold text-primary">1.</span><span>Chọn tối đa 50 câu hỏi đã xuất bản.</span></li>
                    <li class="flex gap-2"><span class="font-semibold text-primary">2.</span><span>Đặt tiêu đề, lịch dự kiến và kiểm tra bộ câu hỏi.</span></li>
                    <li class="flex gap-2"><span class="font-semibold text-primary">3.</span><span>Mở Live Studio để kiểm tra thiết bị trước khi bắt đầu.</span></li>
                </ol>
            </section>
            </aside>
        </div>
    </div>

    <script>
        window.classroomQuestionPicker = (searchUrl) => ({
            availableQuestions: [],
            selected: [],
            search: '',
            loading: false,
            searchTimer: null,
            requestSequence: 0,

            scheduleSearch() {
                window.clearTimeout(this.searchTimer);
                this.searchTimer = window.setTimeout(() => this.loadQuestions(), 350);
            },

            async loadQuestions() {
                const sequence = ++this.requestSequence;
                this.loading = true;

                try {
                    const url = new URL(searchUrl, window.location.origin);
                    if (this.search.trim()) {
                        url.searchParams.set('q', this.search.trim());
                    }
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        throw new Error('Không tải được thư viện câu hỏi.');
                    }

                    // Bỏ kết quả cũ nếu người dùng đã nhập một từ khóa mới hơn.
                    if (sequence === this.requestSequence) {
                        const payload = await response.json();
                        this.availableQuestions = payload.data?.questions ?? [];
                    }
                } catch (error) {
                    if (sequence === this.requestSequence) {
                        this.availableQuestions = [];
                    }
                    console.error('[Classroom] question search', error);
                } finally {
                    if (sequence === this.requestSequence) {
                        this.loading = false;
                    }
                }
            },

            add(question) {
                if (this.selected.length >= 50 || this.selected.some(item => item.id === question.id)) {
                    return;
                }
                this.selected.push(question);
            },

            remove(questionId) {
                this.selected = this.selected.filter(question => question.id !== questionId);
            },
        });
    </script>
</x-layouts.teach>
