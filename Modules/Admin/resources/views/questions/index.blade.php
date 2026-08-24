@php
    $defaultColumns = [
        'taxonomy' => true,
        'difficulty' => false,
        'creator' => false,
        'status' => true,
        'access' => true,
        'attempts' => true,
        'correct_rate' => false,
        'reports' => false,
    ];
@endphp

<x-layouts.admin :title="$isReviewer ? 'Ngân hàng câu hỏi' : 'Câu hỏi của tôi'">
    <div
        x-data="questionColumnPrefs({
            storageKey: 'admin.questions.columns.v1',
            defaults: @js($defaultColumns),
            isReviewer: @js($isReviewer),
        })"
    >
    <x-admin.page-header :title="$isReviewer ? 'Ngân hàng câu hỏi' : 'Câu hỏi của tôi'"
        :description="$isReviewer ? 'Quản lý, kiểm duyệt và xuất bản câu hỏi trong hệ thống.' : 'Bạn chỉ nhìn thấy các câu hỏi do chính mình tạo.'">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('admin.questions.create') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary shadow-sm transition-all hover:bg-primary/90 hover:shadow">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Tạo câu hỏi mới
                </a>
            @endif
            <div class="relative z-[70]" @keydown.escape.window="open = false">
                <button type="button"
                    x-ref="columnTrigger"
                    @click="open = !open"
                    class="inline-flex items-center gap-2 rounded-xl border border-outline-variant bg-surface px-4 py-2.5 font-label-md font-semibold text-on-surface shadow-sm transition-colors hover:bg-surface-container-low"
                    :aria-expanded="open"
                    aria-haspopup="true">
                    <span class="material-symbols-outlined text-[20px]">view_column</span>
                    Cột hiển thị
                </button>
                <template x-teleport="body">
                    <div x-show="open"
                        x-cloak
                        x-transition.opacity.duration.100ms
                        class="fixed inset-0 z-[80]"
                        aria-hidden="true">
                        <div class="absolute inset-0 bg-transparent" @click="open = false"></div>
                        <div
                            @click.stop
                            :style="panelStyle"
                            class="absolute w-64 rounded-2xl border border-outline-variant bg-surface p-3 shadow-xl"
                            role="dialog"
                            aria-label="Chọn cột hiển thị">
                            <p class="mb-2 px-1 font-label-sm font-semibold text-on-surface-variant">Chọn cột cần xem</p>
                            <div class="space-y-1">
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-on-surface hover:bg-surface-container-low">
                                    <input type="checkbox" checked disabled class="size-4 rounded text-primary opacity-60">
                                    Nội dung câu hỏi
                                </label>
                                <template x-for="opt in toggleableColumns" :key="opt.key">
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-on-surface hover:bg-surface-container-low"
                                        x-show="opt.key !== 'creator' || isReviewer">
                                        <input type="checkbox" class="size-4 rounded text-primary focus:ring-primary"
                                            :checked="cols[opt.key]"
                                            @change="toggle(opt.key)">
                                        <span x-text="opt.label"></span>
                                    </label>
                                </template>
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-on-surface hover:bg-surface-container-low">
                                    <input type="checkbox" checked disabled class="size-4 rounded text-primary opacity-60">
                                    Thao tác
                                </label>
                            </div>
                            <button type="button" @click="reset()"
                                class="mt-2 w-full rounded-xl border border-outline-variant px-3 py-2 text-xs font-semibold text-on-surface-variant hover:bg-surface-container-low">
                                Đặt lại mặc định
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <!-- Summary Stats Bar -->
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <span class="material-symbols-outlined text-[22px]">help_center</span>
                </div>
                <div>
                    <p class="text-label-sm font-medium text-on-surface-variant">Tổng số câu hỏi</p>
                    <p class="text-headline-sm font-bold text-on-surface">{{ number_format($stats['total']) }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-success/10 text-success">
                    <span class="material-symbols-outlined text-[22px]">check_circle</span>
                </div>
                <div>
                    <p class="text-label-sm font-medium text-on-surface-variant">Đã xuất bản</p>
                    <p class="text-headline-sm font-bold text-on-surface">
                        {{ number_format($stats['published']) }}
                    </p>
                </div>
            </div>
        </div>
        <a href="{{ route('admin.questions.index', ['status' => 'in_review']) }}"
            class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm transition-colors hover:border-warning/40 hover:bg-warning/5">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-warning/10 text-warning">
                    <span class="material-symbols-outlined text-[22px]">hourglass_empty</span>
                </div>
                <div>
                    <p class="text-label-sm font-medium text-on-surface-variant">Chờ duyệt</p>
                    <p class="text-headline-sm font-bold text-on-surface">
                        {{ number_format($stats['pending']) }}
                    </p>
                </div>
            </div>
        </a>
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-secondary/10 text-secondary">
                    <span class="material-symbols-outlined text-[22px]">stars</span>
                </div>
                <div>
                    <p class="text-label-sm font-medium text-on-surface-variant">Miễn phí</p>
                    <p class="text-headline-sm font-bold text-on-surface">
                        {{ number_format($stats['free']) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="mb-6 rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
        <form method="get" action="{{ route('admin.questions.index') }}" class="grid grid-cols-1 items-end gap-4 sm:grid-cols-12">
            <div class="sm:col-span-3">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="q">Tìm kiếm từ khóa</label>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
                    <input id="q" name="q" value="{{ $filters['q'] }}" type="search"
                        placeholder="Mã hoặc nội dung câu hỏi..."
                        class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest pr-3 pl-9 font-body-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                </div>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="status">Trạng thái</label>
                <select id="status" name="status" class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 font-body-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                    <option value="">Tất cả trạng thái</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="difficulty">Độ khó</label>
                <select id="difficulty" name="difficulty" class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 font-body-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                    <option value="">Tất cả độ khó</option>
                    @foreach ($difficulties as $difficulty)
                        <option value="{{ $difficulty->value }}" @selected($filters['difficulty'] === $difficulty->value)>{{ $difficulty->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="is_free">Truy cập</label>
                <select id="is_free" name="is_free" class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 font-body-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                    <option value="">Tất cả</option>
                    <option value="1" @selected($filters['is_free'] === '1')>Miễn phí</option>
                    <option value="0" @selected($filters['is_free'] === '0')>Premium</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="has_reports">Báo lỗi</label>
                <select id="has_reports" name="has_reports" class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 font-body-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                    <option value="">Tất cả</option>
                    <option value="1" @selected(($filters['has_reports'] ?? null) === '1')>Có báo lỗi</option>
                </select>
            </div>
            <div class="sm:col-span-1 flex gap-2">
                <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-primary font-label-md font-semibold text-on-primary hover:bg-primary/90">
                    Lọc
                </button>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left font-body-sm text-on-surface">
                <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                    <tr>
                        <th class="sticky left-0 z-10 bg-surface-container-low px-5 py-3.5 shadow-[1px_0_0_0_var(--color-outline-variant)]">Nội dung câu hỏi</th>
                        <th class="px-4 py-3.5" x-show="cols.taxonomy" x-cloak>Danh mục y khoa</th>
                        <th class="px-4 py-3.5" x-show="cols.difficulty" x-cloak>Độ khó</th>
                        @if ($isReviewer)
                            <th class="px-4 py-3.5" x-show="cols.creator" x-cloak>Người tạo</th>
                        @endif
                        <th class="px-4 py-3.5" x-show="cols.status" x-cloak>Trạng thái</th>
                        <th class="px-4 py-3.5" x-show="cols.access" x-cloak>Truy cập</th>
                        <th class="px-4 py-3.5 text-end" x-show="cols.attempts" x-cloak>Lượt làm</th>
                        <th class="px-4 py-3.5 text-end" x-show="cols.correct_rate" x-cloak>% đúng</th>
                        <th class="px-4 py-3.5 text-end" x-show="cols.reports" x-cloak>Báo lỗi</th>
                        <th class="sticky right-0 z-10 bg-surface-container-low px-5 py-3.5 text-end shadow-[-1px_0_0_0_var(--color-outline-variant)]">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse ($questions as $question)
                        @php $listStats = $question->listStats(); @endphp
                        <tr class="group/row transition-colors hover:bg-surface-container-lowest">
                            <td class="sticky left-0 z-10 max-w-md bg-surface px-5 py-4 shadow-[1px_0_0_0_var(--color-outline-variant)] group-hover/row:bg-surface-container-lowest sm:max-w-lg">
                                <a href="{{ route('admin.questions.edit', $question) }}" class="group block">
                                    @if (filled($question->code))
                                        <p class="mb-0.5 font-mono text-xs font-semibold text-on-surface-variant">{{ $question->code }}</p>
                                    @endif
                                    <p class="line-clamp-2 font-medium text-on-surface transition-colors group-hover:text-primary">
                                        {{ \Illuminate\Support\Str::limit(strip_tags($question->stem), 140) }}
                                    </p>
                                    <p class="mt-1 font-label-sm text-on-surface-variant">
                                        Phiên bản {{ $question->version > 0 ? $question->version : '—' }} · Cập nhật {{ $question->updated_at?->diffForHumans() }}
                                    </p>
                                </a>
                            </td>
                            <td class="px-4 py-4" x-show="cols.taxonomy" x-cloak>
                                @if($question->medicalTaxonomyNodes->isNotEmpty())
                                    <div class="flex max-w-56 flex-wrap gap-1">
                                        @foreach ($question->medicalTaxonomyNodes->take(2) as $node)
                                            <span class="inline-flex items-center whitespace-nowrap rounded-lg bg-surface-container-high px-2.5 py-1 text-xs font-semibold text-on-surface">
                                                {{ $node->name }}
                                            </span>
                                        @endforeach
                                        @if ($question->medicalTaxonomyNodes->count() > 2)
                                            <span class="inline-flex items-center rounded-lg bg-primary/10 px-2 py-1 text-xs font-semibold text-primary">
                                                +{{ $question->medicalTaxonomyNodes->count() - 2 }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-on-surface-variant/60">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap" x-show="cols.difficulty" x-cloak>
                                <span class="text-xs font-semibold text-on-surface-variant">
                                    {{ $question->difficulty->label() }}
                                </span>
                            </td>
                            @if ($isReviewer)
                                <td class="px-4 py-4 whitespace-nowrap" x-show="cols.creator" x-cloak>
                                    <span class="font-medium">{{ $question->creator?->name ?? 'Dữ liệu hệ thống' }}</span>
                                </td>
                            @endif
                            <td class="px-4 py-4 whitespace-nowrap" x-show="cols.status" x-cloak>
                                @php
                                    $badgeClass = match($question->status) {
                                        \Modules\QuestionBank\Enums\QuestionStatus::Published => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
                                        \Modules\QuestionBank\Enums\QuestionStatus::InReview => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
                                        \Modules\QuestionBank\Enums\QuestionStatus::Rejected => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
                                        \Modules\QuestionBank\Enums\QuestionStatus::Private => 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
                                        \Modules\QuestionBank\Enums\QuestionStatus::Draft => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                        \Modules\QuestionBank\Enums\QuestionStatus::Retired => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
                                    };
                                @endphp
                                <div class="flex flex-col items-start gap-1">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold {{ $badgeClass }}">
                                        {{ $question->status->label() }}
                                    </span>
                                    @if ($question->pendingReviewRequest && $question->status !== \Modules\QuestionBank\Enums\QuestionStatus::InReview)
                                        @if ($isReviewer)
                                            <a href="{{ route('admin.questions.reviews.show', $question->pendingReviewRequest) }}"
                                                class="inline-flex items-center gap-0.5 text-xs font-semibold text-amber-700 hover:underline">
                                                {{ $question->pendingReviewRequest->action->label() }} chờ duyệt
                                            </a>
                                        @else
                                            <span class="text-xs font-semibold text-amber-700">
                                                {{ $question->pendingReviewRequest->action->label() }} chờ duyệt
                                            </span>
                                        @endif
                                    @elseif ($question->pendingReviewRequest && $isReviewer)
                                        <a href="{{ route('admin.questions.reviews.show', $question->pendingReviewRequest) }}"
                                            class="inline-flex items-center gap-0.5 text-xs font-semibold text-primary hover:underline">
                                            Mở duyệt
                                        </a>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap" x-show="cols.access" x-cloak>
                                @if($question->is_free)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-bold text-primary">
                                        Miễn phí
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-secondary/10 px-2.5 py-0.5 text-xs font-bold text-secondary">
                                        Premium
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-end tabular-nums text-on-surface" x-show="cols.attempts" x-cloak>
                                {{ number_format($listStats['total_attempts']) }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-end tabular-nums" x-show="cols.correct_rate" x-cloak>
                                @if ($listStats['correct_rate'] === null || $listStats['total_attempts'] === 0)
                                    <span class="text-on-surface-variant/60">—</span>
                                @else
                                    {{ number_format($listStats['correct_rate'] * 100, 1) }}%
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-end tabular-nums" x-show="cols.reports" x-cloak>
                                @if ($listStats['total_reports'] > 0)
                                    <span class="inline-flex items-center justify-end rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-bold text-red-800">
                                        {{ number_format($listStats['total_reports']) }}
                                    </span>
                                @else
                                    <span class="text-on-surface-variant/60">0</span>
                                @endif
                            </td>
                            <td class="sticky right-0 z-10 whitespace-nowrap bg-surface px-5 py-4 text-end shadow-[-1px_0_0_0_var(--color-outline-variant)] group-hover/row:bg-surface-container-lowest">
                                <div class="inline-flex items-center gap-3">
                                    <a href="{{ route('admin.questions.stats', $question) }}"
                                        class="inline-flex items-center gap-1 text-sm font-semibold text-on-surface-variant hover:text-primary hover:underline"
                                        title="Thống kê chi tiết">
                                        <span class="material-symbols-outlined text-[16px]">analytics</span>
                                        Thống kê
                                    </a>
                                    <a href="{{ route('admin.questions.edit', $question) }}"
                                        class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">
                                        <span class="material-symbols-outlined text-[16px]">edit</span>
                                        Sửa
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-5 py-12 text-center">
                                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                                    <span class="material-symbols-outlined text-[28px]">search_off</span>
                                </div>
                                <p class="mt-3 font-label-lg font-semibold text-on-surface">Không tìm thấy câu hỏi nào</p>
                                <p class="text-body-sm text-on-surface-variant">Thử thay đổi bộ lọc tìm kiếm hoặc tạo câu hỏi mới.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $questions->links() }}</div>
    </div>

    <script>
        function questionColumnPrefs({ storageKey, defaults, isReviewer }) {
            const toggleableColumns = [
                { key: 'taxonomy', label: 'Danh mục y khoa' },
                { key: 'difficulty', label: 'Độ khó' },
                { key: 'creator', label: 'Người tạo' },
                { key: 'status', label: 'Trạng thái' },
                { key: 'access', label: 'Truy cập' },
                { key: 'attempts', label: 'Lượt làm' },
                { key: 'correct_rate', label: '% đúng' },
                { key: 'reports', label: 'Báo lỗi' },
            ];

            const load = () => {
                try {
                    const raw = localStorage.getItem(storageKey);
                    if (! raw) {
                        return { ...defaults };
                    }
                    return { ...defaults, ...JSON.parse(raw) };
                } catch (e) {
                    return { ...defaults };
                }
            };

            return {
                open: false,
                isReviewer,
                toggleableColumns,
                cols: load(),
                panelStyle: '',
                toggle(key) {
                    this.cols[key] = ! this.cols[key];
                    this.persist();
                },
                reset() {
                    this.cols = { ...defaults };
                    this.persist();
                },
                persist() {
                    try {
                        localStorage.setItem(storageKey, JSON.stringify(this.cols));
                    } catch (e) {}
                },
                placePanel() {
                    const btn = this.$refs.columnTrigger;
                    if (! btn) {
                        return;
                    }
                    const rect = btn.getBoundingClientRect();
                    const panelWidth = 256;
                    const gap = 8;
                    let left = rect.right - panelWidth;
                    left = Math.max(12, Math.min(left, window.innerWidth - panelWidth - 12));
                    const top = Math.min(rect.bottom + gap, window.innerHeight - 12);
                    this.panelStyle = `top:${top}px;left:${left}px;`;
                },
                init() {
                    this.$watch('open', (value) => {
                        if (value) {
                            this.$nextTick(() => this.placePanel());
                        }
                    });
                    window.addEventListener('resize', () => {
                        if (this.open) {
                            this.placePanel();
                        }
                    });
                    window.addEventListener('scroll', () => {
                        if (this.open) {
                            this.placePanel();
                        }
                    }, true);
                },
            };
        }
    </script>
</x-layouts.admin>
