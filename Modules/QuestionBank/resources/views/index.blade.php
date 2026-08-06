@php
    $modeLabels = [
        'study' => 'Học tập',
        'exam' => 'Thi thử',
    ];

    $statusMeta = [
        'active' => [
            'label' => 'Đang làm',
            'textClass' => 'text-primary',
            'dotClass' => 'bg-primary animate-pulse',
        ],
        'paused' => [
            'label' => 'Tạm dừng',
            'textClass' => 'text-amber-600',
            'dotClass' => 'bg-amber-500',
        ],
        'completed' => [
            'label' => 'Hoàn thành',
            'textClass' => 'text-green-700',
            'dotClass' => 'bg-green-600',
        ],
        'expired' => [
            'label' => 'Hết hạn',
            'textClass' => 'text-error',
            'dotClass' => 'bg-error',
        ],
        'abandoned' => [
            'label' => 'Đã bỏ',
            'textClass' => 'text-on-surface-variant',
            'dotClass' => 'bg-outline',
        ],
    ];

    $difficultyLabels = [
        'very_easy' => 'Rất dễ',
        'easy' => 'Dễ',
        'medium' => 'Trung bình',
        'hard' => 'Khó',
        'very_hard' => 'Rất khó',
    ];

    $completionRate = $stats['total_sessions'] > 0
        ? (int) round($stats['completed_sessions'] / $stats['total_sessions'] * 100)
        : 0;

    $statCards = [
        [
            'label' => 'Tổng phiên',
            'value' => number_format($stats['total_sessions'], 0, ',', '.'),
            'hint' => 'Toàn bộ lịch sử luyện tập',
            'icon' => 'history',
            'iconWrap' => 'bg-primary/10 text-primary',
        ],
        [
            'label' => 'Đã hoàn thành',
            'value' => number_format($stats['completed_sessions'], 0, ',', '.'),
            'hint' => $completionRate . '% tổng số phiên',
            'icon' => 'task_alt',
            'iconWrap' => 'bg-green-100 text-green-700',
        ],
        [
            'label' => 'Độ chính xác',
            'value' => number_format($stats['accuracy'], 1, ',', '.') . '%',
            'hint' => 'Trên các câu đã trả lời',
            'icon' => 'check_circle',
            'iconWrap' => 'bg-secondary/10 text-secondary',
        ],
        [
            'label' => 'Câu hỏi đã làm',
            'value' => number_format($stats['answered_questions'], 0, ',', '.'),
            'hint' => 'Tổng lượt đã trả lời',
            'icon' => 'quiz',
            'iconWrap' => 'bg-tertiary/10 text-tertiary',
        ],
    ];

    $presentSession = static function ($session) use (
        $difficultyLabels,
        $modeLabels,
        $statusMeta,
    ): array {
        $mode = $session->mode->value;
        $status = $session->status->value;
        $filters = $session->filters ?? [];
        $answered = (int) $session->answered_count;
        $total = (int) $session->total;
        $accuracy = $answered > 0
            ? round((int) $session->correct_count / $answered * 100, 1)
            : 0.0;
        $details = [];
        $topicCount = count($filters['topic_ids'] ?? []);

        if ($topicCount > 0) {
            $details[] = $topicCount . ' chủ đề';
        }

        $difficulties = array_values((array) ($filters['difficulties'] ?? []));
        $legacyDifficulty = $filters['difficulty'] ?? null;
        if ($difficulties === [] && is_string($legacyDifficulty) && $legacyDifficulty !== '') {
            $difficulties = [$legacyDifficulty];
        }
        $selectedDifficultyLabels = array_values(array_intersect_key($difficultyLabels, array_flip($difficulties)));
        if ($selectedDifficultyLabels !== [] && count($selectedDifficultyLabels) < count($difficultyLabels)) {
            $details[] = 'Độ khó ' . mb_strtolower(implode(', ', $selectedDifficultyLabels));
        }

        $questionStatusCount = count($filters['question_statuses'] ?? []);
        if ($questionStatusCount > 0) {
            $details[] = $questionStatusCount . ' trạng thái câu hỏi';
        }

        if ((bool) ($filters['saved_only'] ?? false)) {
            $details[] = 'Chỉ câu đã lưu';
        }

        return [
            'title' => $session->displayName(),
            'subtitle' => $details !== [] ? implode(' · ', $details) : 'Tất cả nội dung phù hợp',
            'date' => $session->created_at?->format('d/m/Y H:i') ?? '—',
            'modeLabel' => $modeLabels[$mode] ?? ucfirst($mode),
            'mode' => $mode,
            'modeClass' => $mode === 'exam'
                ? 'bg-secondary/10 text-secondary'
                : 'bg-primary/10 text-primary',
            'status' => $status,
            'statusLabel' => $statusMeta[$status]['label'] ?? ucfirst($status),
            'statusClass' => $statusMeta[$status]['textClass'] ?? 'text-on-surface-variant',
            'dotClass' => $statusMeta[$status]['dotClass'] ?? 'bg-outline',
            'answered' => $answered,
            'total' => $total,
            'accuracy' => $accuracy,
            'accuracyLabel' => $answered > 0
                ? number_format($accuracy, 1, ',', '.') . '%'
                : '—',
            'barClass' => match (true) {
                $accuracy >= 70 => 'bg-primary',
                $accuracy >= 50 => 'bg-amber-500',
                default => 'bg-error',
            },
            'repeatCounts' => $session->repeatStatusCounts(),
        ];
    };
@endphp

<x-layouts.app title="Ngân hàng câu hỏi">
    <section class="mx-auto max-w-container-max p-4 sm:p-6 md:p-10" x-data="{
        openMenu: null,
        renameOpen: false,
        repeatOpen: false,
        deleteOpen: false,
        activeSession: {},
        renameName: '',
        selectedStatuses: [],
        questionCount: 1,
        openRename(session) {
            this.activeSession = session;
            this.renameName = session.title;
            this.renameOpen = true;
            this.openMenu = null;
        },
        openRepeat(session) {
            this.activeSession = session;
            const preferred = ['correct_with_hints', 'incorrect'];
            this.selectedStatuses = preferred.filter((status) => Number(session.counts[status] || 0) > 0);
            if (!this.selectedStatuses.length) {
                this.selectedStatuses = ['unanswered', 'correct_with_hints', 'incorrect', 'correct']
                    .filter((status) => Number(session.counts[status] || 0) > 0);
            }
            this.questionCount = Math.max(1, this.repeatAvailable());
            this.repeatOpen = true;
            this.openMenu = null;
        },
        openDelete(session) {
            this.activeSession = session;
            this.deleteOpen = true;
            this.openMenu = null;
        },
        repeatAvailable() {
            const counts = this.activeSession.counts || {};
            return this.selectedStatuses.reduce((total, status) => total + Number(counts[status] || 0), 0);
        },
        syncQuestionCount() {
            this.questionCount = Math.max(1, this.repeatAvailable());
        },
        closeModals() {
            this.renameOpen = false;
            this.repeatOpen = false;
            this.deleteOpen = false;
        },
    }" @keydown.escape.window="openMenu = null; closeModals()">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="mb-2 font-headline-md text-headline-md font-bold text-on-surface">Lịch sử phiên luyện</h1>
                <nav class="flex items-center gap-2 text-label-sm text-on-surface-variant">
                    <a class="transition-colors hover:text-primary" href="{{ route('qbank.index') }}">Ngân hàng câu hỏi</a>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    <span class="font-bold text-primary">Lịch sử phiên luyện</span>
                </nav>
            </div>
            <a href="{{ route('qbank.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-label-md font-bold text-white shadow-md transition-all hover:bg-primary/90 active:scale-95">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Tạo phiên luyện tập
            </a>
        </div>

        @if (session('status'))
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-primary"
                role="status">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                <p class="font-medium">{{ session('status') }}</p>
            </div>
        @endif

        <div class="mb-8 grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-6">
            @foreach ($statCards as $stat)
                <article class="rounded-2xl border border-outline-variant bg-white p-4 shadow-sm sm:p-6">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <span class="text-label-sm font-medium text-on-surface-variant">{{ $stat['label'] }}</span>
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $stat['iconWrap'] }}">
                            <span class="material-symbols-outlined text-[22px]">{{ $stat['icon'] }}</span>
                        </span>
                    </div>
                    <p class="mb-2 text-2xl leading-none font-bold text-on-surface sm:text-[32px]">{{ $stat['value'] }}</p>
                    <p class="text-[11px] leading-4 text-on-surface-variant sm:text-xs">{{ $stat['hint'] }}</p>
                </article>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-sm">
            <div class="border-b border-outline-variant bg-surface-container-lowest p-4 sm:p-6">
                <form method="GET" action="{{ route('qbank.index') }}"
                    class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2 md:max-w-xl">
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-bold text-on-surface-variant">Chế độ</span>
                            <select name="mode"
                                class="w-full rounded-xl border border-outline-variant bg-white px-4 py-2.5 text-body-sm focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">Tất cả chế độ</option>
                                @foreach ($modeOptions as $option)
                                    <option value="{{ $option->value }}" @selected($filters['mode'] === $option->value)>
                                        {{ $modeLabels[$option->value] ?? ucfirst($option->value) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1.5 block text-xs font-bold text-on-surface-variant">Trạng thái</span>
                            <select name="status"
                                class="w-full rounded-xl border border-outline-variant bg-white px-4 py-2.5 text-body-sm focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="">Tất cả trạng thái</option>
                                @foreach ($statusOptions as $option)
                                    <option value="{{ $option->value }}" @selected($filters['status'] === $option->value)>
                                        {{ $statusMeta[$option->value]['label'] ?? ucfirst($option->value) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="flex gap-2">
                        @if ($filters['mode'] !== null || $filters['status'] !== null)
                            <a href="{{ route('qbank.index') }}"
                                class="inline-flex flex-1 items-center justify-center rounded-xl border border-outline-variant px-4 py-2.5 text-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container-low md:flex-none">
                                Xóa lọc
                            </a>
                        @endif
                        <button type="submit"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white transition-colors hover:bg-primary/90 md:flex-none">
                            <span class="material-symbols-outlined text-[18px]">filter_alt</span>
                            Áp dụng
                        </button>
                    </div>
                </form>
            </div>

            @if ($sessions->isEmpty())
                <div class="flex flex-col items-center px-6 py-16 text-center">
                    <span class="mb-4 flex size-16 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <span class="material-symbols-outlined text-[34px]">history</span>
                    </span>
                    <h2 class="mb-2 text-lg font-bold text-on-surface">
                        {{ $filters['mode'] !== null || $filters['status'] !== null ? 'Không có phiên phù hợp' : 'Bạn chưa có phiên luyện tập nào' }}
                    </h2>
                    <p class="mb-6 max-w-md text-sm leading-6 text-on-surface-variant">
                        {{ $filters['mode'] !== null || $filters['status'] !== null
                            ? 'Thử thay đổi bộ lọc để xem các phiên luyện tập khác.'
                            : 'Tạo phiên đầu tiên để bắt đầu luyện câu hỏi và theo dõi tiến độ của bạn.' }}
                    </p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        @if ($filters['mode'] !== null || $filters['status'] !== null)
                            <a href="{{ route('qbank.index') }}"
                                class="rounded-xl border border-outline-variant px-5 py-2.5 text-sm font-bold text-on-surface-variant hover:bg-surface-container-low">
                                Xem tất cả phiên
                            </a>
                        @endif
                        <a href="{{ route('qbank.create') }}"
                            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-primary/90">
                            Tạo phiên luyện tập
                        </a>
                    </div>
                </div>
            @else
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="border-b border-outline-variant bg-surface-container-lowest text-on-surface-variant">
                                <th class="px-6 py-4 text-label-md font-bold whitespace-nowrap">Ngày tạo</th>
                                <th class="px-6 py-4 text-label-md font-bold">Phiên luyện</th>
                                <th class="px-6 py-4 text-center text-label-md font-bold">Chế độ</th>
                                <th class="px-6 py-4 text-center text-label-md font-bold whitespace-nowrap">Tiến độ</th>
                                <th class="px-6 py-4 text-label-md font-bold whitespace-nowrap">Tỉ lệ đúng</th>
                                <th class="px-6 py-4 text-label-md font-bold">Trạng thái</th>
                                <th class="px-6 py-4 text-right text-label-md font-bold">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach ($sessions as $session)
                                @php($row = $presentSession($session))
                                <tr class="transition-colors hover:bg-surface-container-lowest">
                                    <td class="px-6 py-5 text-sm whitespace-nowrap text-on-surface-variant">{{ $row['date'] }}</td>
                                    <td class="min-w-56 px-6 py-5">
                                        <p class="text-sm font-bold text-on-surface">{{ $row['title'] }}</p>
                                        <p class="mt-1 text-[11px] leading-4 text-on-surface-variant">{{ $row['subtitle'] }}</p>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <span class="rounded-lg px-2.5 py-1 text-[10px] font-bold tracking-wider uppercase {{ $row['modeClass'] }}">
                                            {{ $row['modeLabel'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 text-center text-sm font-bold whitespace-nowrap">
                                        {{ $row['answered'] }} / {{ $row['total'] }}
                                    </td>
                                    <td class="min-w-40 px-6 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="h-2 min-w-20 flex-1 overflow-hidden rounded-full bg-surface-container-high">
                                                <div class="h-full rounded-full {{ $row['barClass'] }}"
                                                    style="width: {{ $row['accuracy'] }}%"></div>
                                            </div>
                                            <span class="text-sm font-bold text-on-surface">{{ $row['accuracyLabel'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <span class="flex items-center gap-2 text-sm font-bold whitespace-nowrap {{ $row['statusClass'] }}">
                                            <span class="size-2 rounded-full {{ $row['dotClass'] }}"></span>
                                            {{ $row['statusLabel'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex min-w-44 items-start justify-end gap-2">
                                            <div class="flex min-w-32 flex-col items-stretch gap-2">
                                                @if ($row['status'] === 'active')
                                                    <a href="{{ route('qbank.session', $session) }}"
                                                        class="rounded-xl bg-primary px-4 py-2 text-center text-xs font-bold text-white shadow-sm hover:bg-primary/90">
                                                        Tiếp tục
                                                    </a>
                                                @elseif ($row['status'] === 'paused')
                                                    <form method="POST" action="{{ route('qbank.session.resume', $session) }}">
                                                        @csrf
                                                        <button type="submit"
                                                            class="w-full rounded-xl bg-primary px-4 py-2 text-center text-xs font-bold text-white shadow-sm hover:bg-primary/90">
                                                            Tiếp tục
                                                        </button>
                                                    </form>
                                                @elseif ($row['status'] === 'completed')
                                                    <a href="{{ route('qbank.review', $session) }}"
                                                        class="rounded-xl border-2 border-primary/20 px-4 py-2 text-center text-xs font-bold text-primary hover:border-primary hover:bg-primary/5">
                                                        Xem lại
                                                    </a>
                                                    <a href="{{ route('qbank.summary', $session) }}"
                                                        class="rounded-xl border border-outline-variant px-4 py-2 text-center text-xs font-bold text-on-surface-variant hover:bg-surface-container-low">
                                                        Tổng kết
                                                    </a>
                                                @else
                                                    <span class="rounded-xl bg-surface-container-low px-4 py-2 text-center text-xs font-bold text-on-surface-variant">
                                                        Không còn hoạt động
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="relative" @click.outside="if (openMenu === @js((string) $session->getKey())) openMenu = null">
                                                <button type="button"
                                                    @click.stop="openMenu = openMenu === @js((string) $session->getKey()) ? null : @js((string) $session->getKey())"
                                                    class="flex size-9 items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface"
                                                    aria-label="Mở thao tác phiên">
                                                    <span class="material-symbols-outlined">more_vert</span>
                                                </button>
                                                <div x-show="openMenu === @js((string) $session->getKey())" x-cloak
                                                    x-transition.origin.top.right
                                                    class="absolute top-10 right-0 z-30 w-44 overflow-hidden rounded-xl border border-outline-variant bg-white py-1 shadow-xl">
                                                    <button type="button"
                                                        @click="openRename(@js(['title' => $row['title'], 'renameUrl' => route('qbank.session.rename', $session, absolute: false)]))"
                                                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-surface-container-low">
                                                        <span class="material-symbols-outlined text-[19px]">edit</span>
                                                        Đặt lại tên
                                                    </button>
                                                    <button type="button"
                                                        @click="openRepeat(@js(['title' => $row['title'], 'mode' => $row['mode'], 'repeatUrl' => route('qbank.session.repeat', $session, absolute: false), 'counts' => $row['repeatCounts']]))"
                                                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-surface-container-low">
                                                        <span class="material-symbols-outlined text-[19px]">replay</span>
                                                        Làm lại
                                                    </button>
                                                    <button type="button"
                                                        @click="openDelete(@js(['title' => $row['title'], 'deleteUrl' => route('qbank.session.destroy', $session, absolute: false)]))"
                                                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-error hover:bg-error-container/20">
                                                        <span class="material-symbols-outlined text-[19px]">delete</span>
                                                        Xoá
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-outline-variant md:hidden">
                    @foreach ($sessions as $session)
                        @php($row = $presentSession($session))
                        <article class="p-4 sm:p-5">
                            <div class="mb-4 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="mb-1 text-[11px] font-medium text-on-surface-variant">{{ $row['date'] }}</p>
                                    <h2 class="font-bold text-on-surface">{{ $row['title'] }}</h2>
                                    <p class="mt-1 text-[11px] leading-4 text-on-surface-variant">{{ $row['subtitle'] }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <span class="rounded-lg px-2.5 py-1 text-[10px] font-bold uppercase {{ $row['modeClass'] }}">
                                        {{ $row['modeLabel'] }}
                                    </span>
                                    <div class="relative" @click.outside="if (openMenu === @js('mobile-'.(string) $session->getKey())) openMenu = null">
                                        <button type="button"
                                            @click.stop="openMenu = openMenu === @js('mobile-'.(string) $session->getKey()) ? null : @js('mobile-'.(string) $session->getKey())"
                                            class="flex size-9 items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container-low"
                                            aria-label="Mở thao tác phiên">
                                            <span class="material-symbols-outlined">more_vert</span>
                                        </button>
                                        <div x-show="openMenu === @js('mobile-'.(string) $session->getKey())" x-cloak
                                            x-transition.origin.top.right
                                            class="absolute top-10 right-0 z-30 w-44 overflow-hidden rounded-xl border border-outline-variant bg-white py-1 shadow-xl">
                                            <button type="button"
                                                @click="openRename(@js(['title' => $row['title'], 'renameUrl' => route('qbank.session.rename', $session, absolute: false)]))"
                                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-surface-container-low">
                                                <span class="material-symbols-outlined text-[19px]">edit</span>
                                                Đặt lại tên
                                            </button>
                                            <button type="button"
                                                @click="openRepeat(@js(['title' => $row['title'], 'mode' => $row['mode'], 'repeatUrl' => route('qbank.session.repeat', $session, absolute: false), 'counts' => $row['repeatCounts']]))"
                                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-surface-container-low">
                                                <span class="material-symbols-outlined text-[19px]">replay</span>
                                                Làm lại
                                            </button>
                                            <button type="button"
                                                @click="openDelete(@js(['title' => $row['title'], 'deleteUrl' => route('qbank.session.destroy', $session, absolute: false)]))"
                                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-error hover:bg-error-container/20">
                                                <span class="material-symbols-outlined text-[19px]">delete</span>
                                                Xoá
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4 grid grid-cols-2 gap-4 rounded-xl bg-surface-container-lowest p-3">
                                <div>
                                    <p class="mb-1 text-[11px] text-on-surface-variant">Tiến độ</p>
                                    <p class="text-sm font-bold">{{ $row['answered'] }} / {{ $row['total'] }} câu</p>
                                </div>
                                <div>
                                    <p class="mb-1 text-[11px] text-on-surface-variant">Trạng thái</p>
                                    <span class="flex items-center gap-1.5 text-xs font-bold {{ $row['statusClass'] }}">
                                        <span class="size-2 rounded-full {{ $row['dotClass'] }}"></span>
                                        {{ $row['statusLabel'] }}
                                    </span>
                                </div>
                            </div>

                            <div class="mb-5">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-[11px] text-on-surface-variant">Tỉ lệ đúng</span>
                                    <span class="text-sm font-bold text-on-surface">{{ $row['accuracyLabel'] }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-surface-container-high">
                                    <div class="h-full rounded-full {{ $row['barClass'] }}"
                                        style="width: {{ $row['accuracy'] }}%"></div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row">
                                @if ($row['status'] === 'active')
                                    <a href="{{ route('qbank.session', $session) }}"
                                        class="flex-1 rounded-xl bg-primary px-4 py-3 text-center text-xs font-bold text-white shadow-sm">
                                        Tiếp tục phiên
                                    </a>
                                @elseif ($row['status'] === 'paused')
                                    <form method="POST" action="{{ route('qbank.session.resume', $session) }}" class="flex-1">
                                        @csrf
                                        <button type="submit"
                                            class="w-full rounded-xl bg-primary px-4 py-3 text-center text-xs font-bold text-white shadow-sm">
                                            Tiếp tục phiên
                                        </button>
                                    </form>
                                @elseif ($row['status'] === 'completed')
                                    <a href="{{ route('qbank.review', $session) }}"
                                        class="flex-1 rounded-xl border-2 border-primary/20 px-4 py-3 text-center text-xs font-bold text-primary">
                                        Xem lại
                                    </a>
                                    <a href="{{ route('qbank.summary', $session) }}"
                                        class="flex-1 rounded-xl border border-outline-variant px-4 py-3 text-center text-xs font-bold text-on-surface-variant">
                                        Tổng kết
                                    </a>
                                @else
                                    <span class="flex-1 rounded-xl bg-surface-container-low px-4 py-3 text-center text-xs font-bold text-on-surface-variant">
                                        Phiên không còn hoạt động
                                    </span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="flex flex-col gap-4 border-t border-outline-variant bg-surface-container-lowest p-4 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
                    <p class="text-sm font-medium text-on-surface-variant">
                        Hiển thị {{ $sessions->firstItem() }}–{{ $sessions->lastItem() }} trong {{ $sessions->total() }} phiên
                    </p>
                    @if ($sessions->hasPages())
                        <div class="min-w-0">{{ $sessions->onEachSide(1)->links() }}</div>
                    @endif
                </div>
            @endif
        </div>

        <div x-show="renameOpen" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 p-4"
            @click.self="renameOpen = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true"
                aria-labelledby="rename-session-title">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 id="rename-session-title" class="text-xl font-bold text-on-surface">Đặt lại tên phiên</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">Tên mới sẽ hiển thị trong lịch sử phiên luyện.</p>
                    </div>
                    <button type="button" @click="renameOpen = false"
                        class="flex size-9 items-center justify-center rounded-lg hover:bg-surface-container-low"
                        aria-label="Đóng">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form method="POST" :action="activeSession.renameUrl" class="space-y-5">
                    @csrf
                    @method('PATCH')
                    <label class="block">
                        <span class="mb-2 block text-xs font-bold tracking-wide text-on-surface-variant uppercase">Tên phiên</span>
                        <input type="text" name="name" x-model="renameName" maxlength="120" required
                            class="w-full rounded-xl border border-outline-variant px-4 py-3 focus:border-primary focus:ring-1 focus:ring-primary">
                    </label>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="renameOpen = false"
                            class="rounded-xl border border-outline-variant px-5 py-2.5 text-sm font-bold text-on-surface-variant hover:bg-surface-container-low">
                            Huỷ
                        </button>
                        <button type="submit" :disabled="!renameName.trim()"
                            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">
                            Lưu tên
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="repeatOpen" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 p-4"
            @click.self="repeatOpen = false">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true"
                aria-labelledby="repeat-session-title">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 id="repeat-session-title" class="text-xl font-bold text-on-surface">Làm lại phiên câu hỏi</h2>
                        <p class="mt-1 truncate text-sm font-semibold text-primary" x-text="activeSession.title"></p>
                    </div>
                    <button type="button" @click="repeatOpen = false"
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg hover:bg-surface-container-low"
                        aria-label="Đóng">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form method="POST" :action="activeSession.repeatUrl" class="mt-5">
                    @csrf
                    <fieldset class="border-y border-outline-variant py-4">
                        <legend class="mb-2 text-xs font-bold tracking-wide text-on-surface-variant uppercase">
                            Bao gồm các câu hỏi
                        </legend>
                        @foreach ([
                            ['value' => 'unanswered', 'label' => 'Chưa trả lời', 'dot' => 'bg-outline'],
                            ['value' => 'correct_with_hints', 'label' => 'Trả lời đúng có gợi ý', 'dot' => 'bg-amber-500'],
                            ['value' => 'incorrect', 'label' => 'Trả lời sai', 'dot' => 'bg-error'],
                            ['value' => 'correct', 'label' => 'Trả lời đúng', 'dot' => 'bg-primary'],
                        ] as $repeatStatus)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg px-1 py-2 hover:bg-surface-container-low">
                                <input type="checkbox" name="repeat_statuses[]" value="{{ $repeatStatus['value'] }}"
                                    x-model="selectedStatuses" @change="syncQuestionCount()"
                                    class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                <span class="size-2 rounded-full {{ $repeatStatus['dot'] }}"></span>
                                <span class="flex-1 text-sm">{{ $repeatStatus['label'] }}</span>
                                <span class="text-xs font-bold text-on-surface-variant"
                                    x-text="Number((activeSession.counts || {})[@js($repeatStatus['value'])] || 0)"></span>
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="py-5">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <label for="repeat-question-count"
                                class="text-xs font-bold tracking-wide text-on-surface-variant uppercase">Số câu hỏi</label>
                            <span class="text-sm text-on-surface-variant">
                                Tối đa <strong class="text-on-surface" x-text="repeatAvailable()"></strong> câu
                            </span>
                        </div>
                        <div class="flex items-center gap-4">
                            <input type="range" min="1" :max="Math.max(1, repeatAvailable())"
                                x-model.number="questionCount" :disabled="repeatAvailable() === 0"
                                class="h-2 flex-1 cursor-pointer accent-primary disabled:cursor-not-allowed">
                            <input id="repeat-question-count" type="number" name="question_count" min="1"
                                :max="Math.max(1, repeatAvailable())" x-model.number="questionCount"
                                :disabled="repeatAvailable() === 0"
                                class="w-24 rounded-lg border border-outline-variant px-3 py-2.5 text-center focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>

                        <div x-show="activeSession.mode === 'exam'" x-cloak
                            class="mt-4 flex items-start gap-3 rounded-xl border border-secondary/20 bg-secondary/5 p-3 text-secondary">
                            <span class="material-symbols-outlined mt-0.5 text-xl">timer</span>
                            <div>
                                <p class="text-sm font-bold">Làm lại theo chế độ thi</p>
                                <p class="mt-0.5 text-xs text-on-surface-variant">
                                    Thời gian làm bài:
                                    <strong class="text-on-surface" x-text="Math.max(1, Number(questionCount) || 1) * 2"></strong>
                                    phút — 2 phút cho mỗi câu.
                                </p>
                            </div>
                        </div>
                    </div>

                    <button type="submit"
                        :disabled="repeatAvailable() === 0 || selectedStatuses.length === 0"
                        class="w-full rounded-xl bg-primary px-5 py-3 font-bold text-white shadow-sm hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50">
                        Bắt đầu làm lại
                    </button>
                </form>
            </div>
        </div>

        <div x-show="deleteOpen" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 p-4"
            @click.self="deleteOpen = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="alertdialog" aria-modal="true"
                aria-labelledby="delete-session-title">
                <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-error-container/40 text-error">
                    <span class="material-symbols-outlined">delete</span>
                </div>
                <h2 id="delete-session-title" class="text-xl font-bold text-on-surface">Xoá phiên luyện?</h2>
                <p class="mt-2 text-sm leading-6 text-on-surface-variant">
                    Phiên <strong class="text-on-surface" x-text="activeSession.title"></strong> sẽ bị xoá khỏi lịch sử.
                </p>
                <form method="POST" :action="activeSession.deleteUrl" class="mt-6 flex justify-end gap-3">
                    @csrf
                    @method('DELETE')
                    <button type="button" @click="deleteOpen = false"
                        class="rounded-xl border border-outline-variant px-5 py-2.5 text-sm font-bold text-on-surface-variant hover:bg-surface-container-low">
                        Huỷ
                    </button>
                    <button type="submit"
                        class="rounded-xl bg-error px-5 py-2.5 text-sm font-bold text-white hover:opacity-90">
                        Xoá phiên
                    </button>
                </form>
            </div>
        </div>
    </section>
</x-layouts.app>
