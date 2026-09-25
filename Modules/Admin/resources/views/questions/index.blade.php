@php
    $defaultColumns = [
        'taxonomy' => true,
        'difficulty' => false,
        'creator' => false,
        'status' => true,
        'review_status' => true,
        'origin' => false,
        'access' => true,
        'attempts' => true,
        'correct_rate' => false,
        'reports' => false,
    ];

    $hasActiveFilters = filled($filters['q'])
        || filled($filters['status'])
        || filled($filters['difficulty'])
        || filled($filters['is_free'])
        || filled($filters['created_by'] ?? [])
        || filled($filters['lesson_id'])
        || filled($filters['import_batch_id'] ?? null);
@endphp

<x-layouts.admin :title="$isReviewer ? 'Ngân hàng câu hỏi — Quản trị nội dung' : 'Câu hỏi — Quản trị nội dung'">
    <div x-data="questionColumnPrefs({
            storageKey: 'admin.questions.columns.v1',
            defaults: @js($defaultColumns),
            isReviewer: @js($isReviewer),
            canPublish: @js($canPublish ?? false),
            pageIds: @js($questions->pluck('id')->values()),
            filteredTotal: {{ (int) $questions->total() }},
            exportLimit: {{ (int) $exportLimit }},
            bulkLimit: {{ (int) ($bulkLimit ?? 20) }},
            exportUrl: @js(route(\App\Support\Auth\PortalRoute::content('questions.export'))),
            bulkUrl: @js(route('admin.questions.bulk-transition')),
            csrf: @js(csrf_token()),
            exportQuery: @js(request()->except(['page'])),
        })" class="space-y-6">
        {{-- Header chính chuẩn SEO với thẻ H1 --}}
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-headline-md text-headline-md font-bold tracking-tight text-on-surface">
                    {{ $isReviewer ? 'Ngân hàng câu hỏi' : 'Câu hỏi' }}
                </h1>
                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                    {{ $isReviewer ? 'Quản lý, kiểm duyệt, lọc theo danh mục chuyên khoa và xuất bản câu hỏi y khoa.' : 'Danh sách và theo dõi các câu hỏi do chính bạn biên soạn.' }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.reports.show') && auth()->user()?->can('question.view') && auth()->user()?->can('report.view'))
                    <a href="{{ route('admin.reports.show', ['content', 'review-qa']) }}"
                        class="inline-flex items-center gap-2 rounded-xl border border-outline-variant bg-surface px-4 py-2.5 font-label-md font-semibold text-on-surface shadow-sm transition-colors hover:bg-surface-container-low"
                        title="Báo cáo QA duyệt câu hỏi">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">query_stats</span>
                        QA duyệt
                    </a>
                @endif
                @if ($canCreate)
                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.import'))
<a href="{{ route(\App\Support\Auth\PortalRoute::content('questions.import')) }}" id="btn-import-questions"
                        class="inline-flex items-center gap-2 rounded-xl border border-outline-variant bg-surface px-4 py-2.5 font-label-md font-semibold text-on-surface shadow-sm transition-colors hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">upload</span>
                        Import
                    </a>
@endif
                @endif
                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.export'))
<button type="button" id="btn-export-questions-xlsx" @click="exportAs('xlsx')"
                    class="inline-flex items-center gap-2 rounded-xl border border-outline-variant bg-surface px-4 py-2.5 font-label-md font-semibold text-on-surface shadow-sm transition-colors hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">download</span>
                    Xuất Excel
                </button>
@endif
                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.export'))
<button type="button" id="btn-export-questions-csv" @click="exportAs('csv')"
                    class="inline-flex items-center gap-2 rounded-xl border border-outline-variant bg-surface px-4 py-2.5 font-label-md font-semibold text-on-surface shadow-sm transition-colors hover:bg-surface-container-low">
                    Xuất CSV
                </button>
@endif
                @if ($canCreate)
                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.create'))
<a href="{{ route(\App\Support\Auth\PortalRoute::content('questions.create')) }}" id="btn-create-question"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary shadow-sm transition-all hover:bg-primary/90 hover:shadow">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">add</span>
                        Tạo câu hỏi mới
                    </a>
@endif
                @endif

                <div class="relative" @keydown.escape.window="open = false">
                    <button type="button" id="btn-toggle-columns" x-ref="columnTrigger" @click="open = !open"
                        class="inline-flex items-center gap-2 rounded-xl border border-outline-variant bg-surface px-4 py-2.5 font-label-md font-semibold text-on-surface shadow-sm transition-colors hover:bg-surface-container-low"
                        :aria-expanded="open" aria-haspopup="dialog" aria-label="Tùy chọn cột hiển thị trong bảng">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">view_column</span>
                        Cột hiển thị
                    </button>

                    <template x-teleport="body">
                        <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="fixed inset-0 z-[80]"
                            aria-hidden="true">
                            <div class="absolute inset-0 bg-transparent" @click="open = false"></div>
                            <div @click.stop :style="panelStyle"
                                class="absolute w-64 rounded-2xl border border-outline-variant bg-surface p-3 shadow-xl"
                                role="dialog" aria-label="Chọn cột cần xem">
                                <p class="mb-2 px-1 font-label-sm font-semibold text-on-surface-variant">Chọn cột cần
                                    xem</p>
                                <div class="space-y-1">
                                    <label
                                        class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-on-surface hover:bg-surface-container-low">
                                        <input type="checkbox" checked disabled
                                            class="size-4 rounded text-primary opacity-60">
                                        Nội dung câu hỏi
                                    </label>
                                    <template x-for="opt in toggleableColumns" :key="opt.key">
                                        <label
                                            class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-on-surface hover:bg-surface-container-low">
                                            <input type="checkbox"
                                                class="size-4 rounded text-primary focus:ring-primary"
                                                :checked="cols[opt.key]" @change="toggle(opt.key)">
                                            <span x-text="opt.label"></span>
                                        </label>
                                    </template>
                                    <label
                                        class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-on-surface hover:bg-surface-container-low">
                                        <input type="checkbox" checked disabled
                                            class="size-4 rounded text-primary opacity-60">
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
            </div>
        </header>

        <x-admin.flash />

        @if (filled($exportBanner ?? null))
            <p class="flex flex-wrap items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 font-body-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100">
                <span class="material-symbols-outlined mt-0.5 text-[20px] text-amber-700 dark:text-amber-300" aria-hidden="true">info</span>
                <span>{{ $exportBanner }}</span>
            </p>
        @endif

        @if (($importBatch ?? null) && filled($importBatch->original_filename))
            <p class="flex flex-wrap items-center gap-2 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 font-body-sm text-on-surface">
                <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">draft</span>
                Đang xem câu hỏi từ tệp import:
                <strong class="break-all">{{ $importBatch->original_filename }}</strong>
            </p>
        @endif

        {{-- Section 1: Thống kê tổng quan --}}
        <section id="question-stats" aria-labelledby="heading-stats">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 id="heading-stats" class="font-label-lg font-semibold text-on-surface">Tổng quan</h2>
                <p class="font-body-sm text-on-surface-variant">Tình trạng ngân hàng câu hỏi</p>
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <div class="rounded-xl border border-outline-variant bg-surface p-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-container-low text-on-surface-variant">
                            <span class="material-symbols-outlined text-[22px]" aria-hidden="true">help_center</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-label-sm font-medium text-on-surface-variant">Tổng câu hỏi</p>
                            <p class="text-headline-sm font-bold text-on-surface">{{ number_format($stats['total']) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-outline-variant bg-surface p-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-container-low text-on-surface-variant">
                            <span class="material-symbols-outlined text-[22px]" aria-hidden="true">check_circle</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-label-sm font-medium text-on-surface-variant">Đã xuất bản</p>
                            <p class="text-headline-sm font-bold text-on-surface">
                                {{ number_format($stats['published']) }}</p>
                        </div>
                    </div>
                </div>

                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.index'))
<a href="{{ route(\App\Support\Auth\PortalRoute::content('questions.index'), ['status' => ['in_review', 'in_flag_review']]) }}" id="stats-pending-review-link"
                    class="rounded-xl border border-outline-variant bg-surface p-4 transition-colors hover:bg-surface-container-low"
                    aria-label="Xem các câu hỏi chờ duyệt: {{ number_format($stats['pending']) }} câu">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-container-low text-on-surface-variant">
                            <span class="material-symbols-outlined text-[22px]"
                                aria-hidden="true">hourglass_empty</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-label-sm font-medium text-on-surface-variant">Chờ duyệt</p>
                            <p class="text-headline-sm font-bold text-on-surface">{{ number_format($stats['pending']) }}
                            </p>
                        </div>
                    </div>
                </a>
                <a href="{{ route('admin.questions.index', ['review' => 'must_reject']) }}" id="stats-must-reject-link"
                    class="rounded-xl border border-outline-variant bg-surface p-4 transition-colors hover:bg-surface-container-low {{ ($filters['review'] ?? null) === 'must_reject' ? 'ring-2 ring-amber-400' : '' }}"
                    aria-label="Câu đang cảnh báo cờ: {{ number_format($stats['must_reject'] ?? 0) }} câu">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-800">
                            <span class="material-symbols-outlined text-[22px]" aria-hidden="true">warning</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-label-sm font-medium text-on-surface-variant">Cảnh báo cờ</p>
                            <p class="text-headline-sm font-bold text-on-surface">{{ number_format($stats['must_reject'] ?? 0) }}
                            </p>
                        </div>
                    </div>
                </a>
@endif

                <div class="rounded-xl border border-outline-variant bg-surface p-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-container-low text-on-surface-variant">
                            <span class="material-symbols-outlined text-[22px]" aria-hidden="true">stars</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-label-sm font-medium text-on-surface-variant">Miễn phí</p>
                            <p class="text-headline-sm font-bold text-on-surface">{{ number_format($stats['free']) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Section 2: Bộ lọc tìm kiếm câu hỏi --}}
        <section aria-labelledby="heading-filters">
            <h2 id="heading-filters" class="sr-only">Tìm kiếm câu hỏi</h2>
            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.index'))
            <div>
<form method="get" action="{{ route(\App\Support\Auth\PortalRoute::content('questions.index')) }}" id="question-filter-form" role="search"
                aria-label="Tìm kiếm và lọc câu hỏi"
                @submit.prevent="applyQuestionFilters()"
                class="space-y-4 rounded-xl border border-outline-variant bg-surface p-4">
                @if (filled($filters['import_batch_id'] ?? null))
                    <input type="hidden" name="import_batch_id" value="{{ $filters['import_batch_id'] }}">
                @endif
                <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(280px,1.5fr)_repeat(4,minmax(160px,1fr))]">
                <div class="sm:col-span-2 xl:col-auto">
                    <label class="mb-1.5 block text-sm font-medium text-on-surface-variant"
                        for="question-search-input">
                        Tìm kiếm
                    </label>
                    <div class="relative">
                        <span
                            class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant"
                            aria-hidden="true">search</span>
                        <input id="question-search-input" name="q" value="{{ $filters['q'] }}" type="search"
                            autocomplete="off" placeholder="Mã, từ khóa hoặc nội dung câu hỏi..."
                            class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 pl-9 text-sm text-on-surface outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                    </div>
                </div>

                <div class="min-w-0 xl:col-auto">
                    <x-admin.multi-select-filter
                        name="status"
                        label="Trạng thái"
                        placeholder="Tất cả"
                        :options="collect($statuses)->map(fn ($status) => ['id' => $status->value, 'label' => $status->label(), 'tone' => $status->tone()])->all()"
                        :selected="$filters['status'] ?? []"
                    />
                </div>

                <div class="min-w-0 xl:col-auto">
                    <x-admin.multi-select-filter
                        name="difficulty"
                        label="Độ khó"
                        placeholder="Tất cả"
                        :options="collect($difficulties)->map(fn ($difficulty) => ['id' => $difficulty->value, 'label' => $difficulty->label(), 'tone' => $difficulty->tone()])->all()"
                        :selected="$filters['difficulty'] ?? []"
                    />
                </div>

                <div class="min-w-0 xl:col-auto">
                    <x-admin.multi-select-filter
                        name="is_free"
                        label="Miễn phí"
                        placeholder="Tất cả"
                        :options="[
                            ['id' => '1', 'label' => 'Có', 'tone' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                            ['id' => '0', 'label' => 'Không', 'tone' => 'bg-surface-container-high text-on-surface-variant border-outline-variant'],
                        ]"
                        :selected="$filters['is_free'] ?? []"
                    />
                </div>

                @if ($canViewAny)
                    <div class="min-w-0 xl:col-auto">
                        <x-admin.multi-select-filter
                            name="created_by"
                            label="Người tạo"
                            placeholder="Tất cả"
                            :options="$creatorOptions"
                            :selected="$filters['created_by'] ?? []"
                        />
                    </div>
                @endif
                </div>

                <x-admin.filter-action-buttons class="justify-end border-t border-outline-variant pt-4" loading-expression="ajaxLoading"
                    reset-method="resetQuestionFilters" :reset-url="route(\App\Support\Auth\PortalRoute::content('questions.index'))"
                    search-aria-label="Tìm kiếm câu hỏi" reset-aria-label="Xoá bộ lọc câu hỏi" />
            </form>
            </div>
@endif
        </section>

        <div id="question-results-region">
        {{-- Section 3: Bảng dữ liệu câu hỏi - Scroll trái phải đồng đều --}}
        <section aria-labelledby="heading-questions-list"
            class="overflow-hidden rounded-xl border border-outline-variant bg-surface">

            {{-- Toolbar điều hướng cuộn ngang bảng cân xứng --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant px-5 py-4">
                <div class="flex items-center gap-2 font-medium">
                    <h2 id="heading-questions-list" class="font-label-lg font-semibold text-on-surface">Danh sách câu
                        hỏi</h2>
                    <span>Hiển thị <strong>{{ number_format($questions->count()) }}</strong> /
                        <strong>{{ number_format($questions->total()) }}</strong> câu hỏi</span>
                    @if ($questions->hasPages())
                        <span>· Trang {{ $questions->currentPage() }} / {{ $questions->lastPage() }}</span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <span class="hidden text-[11px] text-on-surface-variant/80 lg:inline">
                        <span class="material-symbols-outlined align-middle text-[14px]"
                            aria-hidden="true">swap_horiz</span>
                        Cuộn ngang xem đầy đủ các cột:
                    </span>
                    <div
                        class="inline-flex items-center rounded-lg border border-outline-variant bg-surface p-0.5 shadow-xs">
                        <button type="button" @click="scrollTable(-300)" :disabled="!canScrollLeft"
                            id="btn-scroll-table-left"
                            class="flex size-7 items-center justify-center rounded text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface disabled:cursor-not-allowed disabled:opacity-30"
                            aria-label="Cuộn bảng sang trái">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_left</span>
                        </button>
                        <span class="h-3.5 w-px bg-outline-variant"></span>
                        <button type="button" @click="scrollTable(300)" :disabled="!canScrollRight"
                            id="btn-scroll-table-right"
                            class="flex size-7 items-center justify-center rounded text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface disabled:cursor-not-allowed disabled:opacity-30"
                            aria-label="Cuộn bảng sang phải">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Khung cuộn ngang mượt mà, đồng đều --}}
            <div x-ref="tableContainer" @scroll.passive="updateScrollState()" id="questions-table-scroll-container"
                class="relative w-full overflow-x-auto scroll-smooth focus:outline-none" tabindex="0"
                aria-label="Vùng cuộn bảng dữ liệu câu hỏi">
                <table id="questions-data-table" aria-label="Bảng danh sách câu hỏi ngân hàng"
                    class="w-full min-w-[1414px] table-fixed border-collapse text-left font-body-sm text-on-surface">
                    <caption class="sr-only">Danh sách câu hỏi ngân hàng, chi tiết độ khó, trạng thái kiểm duyệt và
                        thống kê tỷ lệ đúng</caption>
                    <thead
                        class="border-b border-outline-variant bg-surface-container-low text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                        <tr>
                            <th scope="col" class="w-11 min-w-11 px-3 py-3.5">
                                <label class="sr-only" for="select-all-questions">Chọn tất cả câu trên trang</label>
                                <input id="select-all-questions" type="checkbox"
                                    class="size-4 rounded border-outline-variant text-primary focus:ring-primary"
                                    :checked="allPageSelected"
                                    x-effect="$el.indeterminate = somePageSelected && !allPageSelected"
                                    @change="togglePage($event.target.checked)"
                                    @if ($questions->isEmpty()) disabled @endif>
                            </th>
                            <th scope="col" class="w-[380px] min-w-[320px] px-5 py-3.5">Nội dung câu hỏi</th>
                            <th scope="col" class="w-[220px] min-w-[180px] px-4 py-3.5" x-show="cols.taxonomy" x-cloak>
                                Bài học</th>
                            <th scope="col" class="w-[110px] min-w-[100px] px-4 py-3.5 text-center"
                                x-show="cols.difficulty" x-cloak>Độ khó</th>
                            <th scope="col" class="w-[150px] min-w-[130px] px-4 py-3.5" x-show="cols.creator" x-cloak>
                                    Người tạo</th>
                            <th scope="col" class="w-[140px] min-w-[120px] px-4 py-3.5" x-show="cols.status" x-cloak>
                                Trạng thái</th>
                            <th scope="col" class="w-[180px] min-w-[160px] px-4 py-3.5" x-show="cols.review_status"
                                x-cloak title="Editor đã gửi bản cập nhật chưa, và 2 giảng viên đã duyệt thế nào.">
                                Bản gửi duyệt</th>
                            <th scope="col" class="w-[160px] min-w-[140px] px-4 py-3.5" x-show="cols.origin" x-cloak>
                                Nguồn gốc</th>
                            <th scope="col" class="w-[110px] min-w-[100px] px-4 py-3.5 text-center" x-show="cols.access"
                                x-cloak>Miễn phí</th>
                            <th scope="col" class="w-[110px] min-w-[90px] px-4 py-3.5 text-end" x-show="cols.attempts"
                                x-cloak>Lượt làm</th>
                            <th scope="col" class="w-[100px] min-w-[90px] px-4 py-3.5 text-end"
                                x-show="cols.correct_rate" x-cloak>% đúng</th>
                            <th scope="col" class="w-[100px] min-w-[90px] px-4 py-3.5 text-end whitespace-nowrap"
                                x-show="cols.reports" x-cloak>Phản hồi</th>
                            <th scope="col" class="w-[280px] min-w-[280px] px-5 py-3.5 text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        @forelse ($questions as $question)
                            @php
                                $listStats = $question->listStats();
                                $canUpdateQuestion = \Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.update');
                            @endphp
                            <tr class="transition-colors hover:bg-surface-container-low"
                                :class="isSelected(@js($question->getKey())) && 'bg-primary/5'">
                                <td class="w-11 min-w-11 px-3 py-4 align-top">
                                    <input type="checkbox"
                                        class="mt-1 size-4 rounded border-outline-variant text-primary focus:ring-primary"
                                        :checked="isSelected(@js($question->getKey()))"
                                        @change="toggleOne(@js($question->getKey()), $event.target.checked)"
                                        aria-label="Chọn câu {{ $question->code ?: $question->id }}">
                                </td>
                                <td class="w-[380px] min-w-[320px] px-5 py-4 align-top">
                                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.edit'))
<a href="{{ route(\App\Support\Auth\PortalRoute::content('questions.edit'), $question) }}" class="group block"
                                        aria-label="{{ $canUpdateQuestion ? 'Chỉnh sửa' : 'Xem chi tiết' }} câu hỏi {{ $question->code ?: $question->id }}">
                                        @if (filled($question->code))
                                            <p
                                                class="mb-0.5 font-mono text-xs font-semibold text-on-surface-variant group-hover:underline"
                                                title="Mã câu hỏi">
                                                {{ $question->code }}
                                            </p>
                                        @endif
                                        <p class="line-clamp-2 font-medium leading-snug text-on-surface transition-colors group-hover:underline"
                                            title="{{ strip_tags($question->stem) }}">
                                            {{ \Illuminate\Support\Str::limit(strip_tags($question->stem), 140) }}
                                        </p>
                                        <p class="mt-1 font-label-sm text-on-surface-variant">
                                            @php
                                                $versionAt = filled($question->version_updated_at ?? null)
                                                    ? \Illuminate\Support\Carbon::parse($question->version_updated_at)
                                                    : null;
                                            @endphp
                                            {{ $question->version > 0 ? 'Phiên bản ' . $question->version : 'Chưa có phiên bản' }}
                                            @if ($versionAt)
                                                · Cập nhật {{ $versionAt->diffForHumans() }}
                                            @endif
                                        </p>
                                    </a>
@endif
                                </td>

                                <td class="w-[220px] min-w-[180px] px-4 py-4 align-top" x-show="cols.taxonomy" x-cloak>
                                    @if($question->lessons->isNotEmpty())
                                        <div class="flex max-w-full flex-wrap gap-1 overflow-hidden">
                                            @foreach ($question->lessons->take(2) as $lesson)
                                                <span
                                                    class="inline-flex max-w-full items-center truncate rounded-md bg-surface-container-high px-2 py-0.5 text-xs font-medium text-on-surface"
                                                    title="{{ $lesson->name }}">
                                                    {{ $lesson->name }}
                                                </span>
                                            @endforeach
                                            @if ($question->lessons->count() > 2)
                                                <span
                                                    class="inline-flex items-center rounded-md border border-outline-variant px-1.5 py-0.5 text-xs font-semibold text-on-surface-variant"
                                                    title="{{ $question->lessons->slice(2)->pluck('name')->join(', ') }}">
                                                    +{{ $question->lessons->count() - 2 }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-on-surface-variant/60">—</span>
                                    @endif
                                </td>

                                <td class="w-[110px] min-w-[100px] px-4 py-4 text-center align-top whitespace-nowrap"
                                    x-show="cols.difficulty" x-cloak>
                                    <span
                                        class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $question->difficulty->tone() }}">
                                        {{ $question->difficulty->label() }}
                                    </span>
                                </td>

                                <td class="w-[150px] min-w-[130px] px-4 py-4 align-top whitespace-nowrap"
                                        x-show="cols.creator" x-cloak>
                                        <span
                                            class="text-xs font-medium text-on-surface">{{ $question->creator?->name ?? 'Dữ liệu hệ thống' }}</span>
                                    </td>

                                {{-- Trạng thái workflow, đồng bộ với bộ lọc QuestionStatus --}}
                                <td class="w-[140px] min-w-[120px] px-4 py-4 align-top whitespace-nowrap"
                                    x-show="cols.status" x-cloak>
                                    @php
                                        $statusLabel = $question->status->label();
                                        if ((int) $question->published_version > 0 && $question->status === \Modules\QuestionBank\Enums\QuestionStatus::Published) {
                                            $statusLabel .= ' · v'.$question->published_version;
                                        }
                                    @endphp
                                    <span
                                        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $question->status->tone() }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                {{-- Cột 2: Editor đã gửi bản cập nhật chưa + phiếu 2 GV --}}
                                <td class="w-[180px] min-w-[160px] px-4 py-4 align-top"
                                    x-show="cols.review_status" x-cloak>
                                    <div class="flex flex-col items-start gap-1">
                                        <p class="text-[11px] font-medium leading-4 text-on-surface-variant">
                                            {{ $question->editorialSubmissionLabel() }}
                                        </p>
                                        @if ($label = $question->pipelineProgressLabel())
                                            <p class="text-[10px] font-semibold leading-4 text-on-surface">{{ $label }}</p>
                                        @endif
                                        @if ($question->hasEditorialSubmission())
                                            @include('questionbank::partials.instructor-review-flags', ['question' => $question])
                                        @endif
                                        @if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::PendingPublish && $isReviewer)
                                            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.edit'))
<a href="{{ route(\App\Support\Auth\PortalRoute::content('questions.edit'), $question) }}"
                                                class="inline-flex items-center gap-0.5 text-xs font-semibold text-primary hover:underline">
                                                Duyệt xuất bản
                                            </a>
@endif
                                        @endif
                                    </div>
                                </td>

                                {{-- Cột Nguồn gốc --}}
                                <td class="w-[160px] min-w-[140px] px-4 py-4 align-top" x-show="cols.origin" x-cloak>
                                    @if ($question->cloned_from_id === null)
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full border border-outline-variant px-2.5 py-0.5 text-xs font-medium text-on-surface">
                                            <span class="material-symbols-outlined text-[12px]" aria-hidden="true">eco</span>
                                            Câu hỏi gốc
                                        </span>
                                    @else
                                        @php
                                            $origin = $question->clonedFrom;
                                            $originLabel = $origin?->code ?: ($origin ? \Illuminate\Support\Str::limit(strip_tags($origin->stem), 30) : 'ID: ' . substr($question->cloned_from_id, 0, 8) . '…');
                                        @endphp
                                        <div class="flex flex-col gap-0.5">
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full border border-outline-variant px-2.5 py-0.5 text-xs font-medium text-on-surface self-start">
                                                <span class="material-symbols-outlined text-[12px]"
                                                    aria-hidden="true">content_copy</span>
                                                Bản sao
                                            </span>
                                            @if ($origin)
                                                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.edit'))
<a href="{{ route(\App\Support\Auth\PortalRoute::content('questions.edit'), $origin) }}"
                                                    class="mt-0.5 text-[11px] font-medium text-primary hover:underline truncate max-w-[140px] block"
                                                    title="Xem câu hỏi gốc: {{ strip_tags($origin->stem) }}"
                                                    aria-label="Xem câu hỏi gốc {{ $originLabel }}">
                                                    {{ $originLabel }}
                                                </a>
@endif
                                            @else
                                                <span class="mt-0.5 text-[11px] text-on-surface-variant/60 italic">Câu gốc đã
                                                    xóa</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td class="w-[110px] min-w-[100px] px-4 py-4 text-center align-top whitespace-nowrap"
                                    x-show="cols.access" x-cloak>
                                    @if($question->is_free)
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-800 border-emerald-200">
                                            Có
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium bg-surface-container-high text-on-surface-variant border-outline-variant">
                                            Không
                                        </span>
                                    @endif
                                </td>

                                <td class="w-[110px] min-w-[90px] px-4 py-4 text-end align-top tabular-nums whitespace-nowrap text-on-surface"
                                    x-show="cols.attempts" x-cloak>
                                    {{ number_format($listStats['total_attempts']) }}
                                </td>

                                <td class="w-[100px] min-w-[90px] px-4 py-4 text-end align-top tabular-nums whitespace-nowrap"
                                    x-show="cols.correct_rate" x-cloak>
                                    @if ($listStats['correct_rate'] === null || $listStats['total_attempts'] === 0)
                                        <span class="text-on-surface-variant/60">—</span>
                                    @else
                                        {{ number_format($listStats['correct_rate'] * 100, 1) }}%
                                    @endif
                                </td>

                                <td class="w-[100px] min-w-[90px] px-4 py-4 text-end align-top tabular-nums whitespace-nowrap"
                                    x-show="cols.reports" x-cloak>
                                    @php
                                        $realFeedback = (int) ($question->feedback_count ?? 0);
                                        $pendingFeedback = (int) ($question->pending_feedback_count ?? 0);
                                        $totalFeedback = max($realFeedback, (int) ($listStats['total_reports'] ?? 0));
                                    @endphp
                                    @if ($totalFeedback > 0)
                                        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.question-feedback.index'))
<a href="{{ route('admin.question-feedback.index', ['question_id' => $question->id]) }}"
                                            class="inline-flex items-center gap-1 rounded-full border border-outline-variant px-2.5 py-0.5 text-xs font-medium text-on-surface transition hover:bg-surface-container-low"
                                            title="{{ $pendingFeedback > 0 ? $pendingFeedback . ' phản hồi chờ xử lý' : 'Xem ' . $totalFeedback . ' phản hồi' }}">
                                            @if ($pendingFeedback > 0)
                                                <span class="size-1.5 rounded-full bg-on-surface" aria-hidden="true"></span>
                                            @endif
                                            {{ number_format($totalFeedback) }}
                                        </a>
@endif
                                    @else
                                        <span class="text-on-surface-variant/50">0</span>
                                    @endif
                                </td>

                                <td class="w-[280px] min-w-[280px] px-5 py-4 text-end align-top whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2.5">
                                        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.stats'))
<a href="{{ route(\App\Support\Auth\PortalRoute::content('questions.stats'), $question) }}"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-on-surface-variant hover:text-on-surface hover:underline"
                                            title="Xem thống kê làm bài câu hỏi">
                                            <span class="material-symbols-outlined text-[15px]"
                                                aria-hidden="true">analytics</span>
                                            Thống kê
                                        </a>
@endif
                                        @if ($canUpdateQuestion)
<a href="{{ route(\App\Support\Auth\PortalRoute::content('questions.edit'), $question) }}"
                                            class="inline-flex items-center gap-1 rounded-md border border-outline-variant px-2 py-1 text-xs font-medium text-on-surface hover:bg-surface-container-low"
                                            title="Sửa nội dung câu hỏi">
                                            <span class="material-symbols-outlined text-[15px]"
                                                aria-hidden="true">edit</span>
                                            Sửa
                                        </a>
@endif
                                        @if (! request()->routeIs('editor.*'))
                                        @can(\App\Support\Enums\Permission::QuestionCreate->value)
                                            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.clone'))
<form method="post" action="{{ route('admin.questions.clone', $question) }}"
                                                class="inline">
                                                @csrf
                                                <button type="submit"
                                                    onclick="return confirm('Tạo bản sao mới từ câu hỏi này?')"
                                                    class="inline-flex items-center gap-1 text-xs font-medium text-on-surface-variant hover:text-on-surface hover:underline"
                                                    title="Nhân bản câu hỏi thành bản nháp mới">
                                                    <span class="material-symbols-outlined text-[15px]"
                                                        aria-hidden="true">content_copy</span>
                                                    Nhân bản
                                                </button>
                                            </form>
@endif
                                        @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-5 py-12 text-center">
                                    <div
                                        class="mx-auto flex size-12 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                                        <span class="material-symbols-outlined text-[28px]"
                                            aria-hidden="true">search_off</span>
                                    </div>
                                    <p class="mt-3 font-label-lg font-semibold text-on-surface">Không tìm thấy câu hỏi nào
                                    </p>
                                    <p class="mt-1 text-body-sm text-on-surface-variant">Thử điều chỉnh hoặc xóa bộ lọc tìm
                                        kiếm để xem danh sách câu hỏi.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Section 4: Phân trang chuẩn semantic --}}
        @if ($questions->hasPages())
            <nav aria-label="Điều hướng phân trang danh sách câu hỏi" class="pt-2">
                {{ $questions->links() }}
            </nav>
        @endif
        </div>

        <form x-ref="exportForm" method="post" action="{{ route(\App\Support\Auth\PortalRoute::content('questions.export')) }}" class="hidden">
            @csrf
            <input type="hidden" name="format" :value="exportFormat">
            @foreach (request()->except(['page', 'ids', 'format']) as $key => $value)
                @if (is_array($value))
                    @foreach ($value as $item)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                    @endforeach
                @elseif (filled($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <template x-for="id in selectedIds" :key="id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
        </form>

        <div x-show="selectedCount > 0" x-cloak
            class="sticky bottom-4 z-40 mx-auto flex w-full max-w-4xl flex-wrap items-center justify-between gap-3 rounded-2xl border border-outline-variant bg-surface px-4 py-3 shadow-lg">
            <p class="font-label-md text-on-surface">
                <strong x-text="selectedCount"></strong> câu đã chọn
                <span class="text-on-surface-variant" x-show="selectedCount > exportLimit">
                    — xuất tối đa <strong x-text="exportLimit"></strong>
                </span>
                <span class="text-on-surface-variant" x-show="canPublish && selectedCount > bulkLimit">
                    — XB hàng loạt tối đa <strong x-text="bulkLimit"></strong>/lần (chỉ 1 vòng + 2 xanh)
                </span>
            </p>
            <div class="flex flex-wrap items-center gap-2">
                @if (! empty($canPublish))
                    <button type="button" @click="bulkPublish()" :disabled="bulkBusy"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3 py-2 font-label-sm font-semibold text-on-primary hover:bg-primary/90 disabled:opacity-60"
                        title="Chỉ xuất bản câu chờ XB, đúng 1 vòng và đủ 2 cờ xanh">
                        <span class="material-symbols-outlined text-[16px]">publish</span>
                        Xuất bản
                    </button>
                @endif
                <button type="button" @click="exportAs('xlsx')"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant px-3 py-2 font-label-sm font-semibold text-on-surface hover:bg-surface-container-low">
                    Xuất Excel
                </button>
                <button type="button" @click="exportAs('csv')"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant px-3 py-2 font-label-sm font-semibold text-on-surface hover:bg-surface-container-low">
                    Xuất CSV
                </button>
                <button type="button" @click="clearSelected()"
                    class="font-label-sm font-semibold text-on-surface-variant hover:text-on-surface hover:underline">
                    Bỏ chọn
                </button>
            </div>
        </div>
    </div>

    <script>
        function questionColumnPrefs({ storageKey, defaults, isReviewer, canPublish, pageIds, filteredTotal, exportLimit, bulkLimit, exportUrl, bulkUrl, csrf, exportQuery }) {
            const toggleableColumns = [
                { key: 'taxonomy', label: 'Bài học' },
                { key: 'difficulty', label: 'Độ khó' },
                { key: 'creator', label: 'Người tạo' },
                { key: 'status', label: 'Trạng thái' },
                { key: 'review_status', label: 'Bản gửi duyệt' },
                { key: 'origin', label: 'Nguồn gốc' },
                { key: 'access', label: 'Miễn phí' },
                { key: 'attempts', label: 'Lượt làm' },
                { key: 'correct_rate', label: '% đúng' },
                { key: 'reports', label: 'Phản hồi' },
            ];

            const load = () => {
                try {
                    const raw = localStorage.getItem(storageKey);
                    if (!raw) {
                        return { ...defaults };
                    }
                    return { ...defaults, ...JSON.parse(raw) };
                } catch (e) {
                    return { ...defaults };
                }
            };

            const selectionKey = 'admin.questions.export-ids.v1';
            const loadSelected = () => {
                try {
                    const raw = sessionStorage.getItem(selectionKey);
                    return raw ? JSON.parse(raw) : [];
                } catch (e) {
                    return [];
                }
            };

            return {
                open: false,
                ajaxLoading: false,
                isReviewer,
                canPublish: Boolean(canPublish),
                toggleableColumns,
                cols: load(),
                panelStyle: '',
                canScrollLeft: false,
                canScrollRight: false,
                pageIds: pageIds || [],
                filteredTotal: filteredTotal || 0,
                exportLimit: exportLimit || 2000,
                bulkLimit: bulkLimit || 20,
                exportUrl,
                bulkUrl,
                csrf,
                exportQuery: exportQuery || {},
                exportFormat: 'xlsx',
                selectedIds: loadSelected(),
                bulkBusy: false,
                toggle(key) {
                    this.cols[key] = !this.cols[key];
                    this.persist();
                    this.$nextTick(() => this.updateScrollState());
                },
                reset() {
                    this.cols = { ...defaults };
                    this.persist();
                    this.$nextTick(() => this.updateScrollState());
                },
                persist() {
                    try {
                        localStorage.setItem(storageKey, JSON.stringify(this.cols));
                    } catch (e) { }
                },
                get selectedCount() {
                    return this.selectedIds.length;
                },
                get allPageSelected() {
                    return this.pageIds.length > 0 && this.pageIds.every((id) => this.selectedIds.includes(id));
                },
                get somePageSelected() {
                    return this.pageIds.some((id) => this.selectedIds.includes(id));
                },
                isSelected(id) {
                    return this.selectedIds.includes(id);
                },
                persistSelected() {
                    try {
                        sessionStorage.setItem(selectionKey, JSON.stringify(this.selectedIds.slice(0, this.exportLimit)));
                    } catch (e) { }
                },
                toggleOne(id, checked) {
                    if (checked && !this.selectedIds.includes(id)) {
                        if (this.selectedIds.length >= this.exportLimit) {
                            return;
                        }
                        this.selectedIds = [...this.selectedIds, id];
                    }
                    if (!checked) {
                        this.selectedIds = this.selectedIds.filter((item) => item !== id);
                    }
                    this.persistSelected();
                },
                togglePage(checked) {
                    if (checked) {
                        const next = [...this.selectedIds];
                        this.pageIds.forEach((id) => {
                            if (!next.includes(id) && next.length < this.exportLimit) {
                                next.push(id);
                            }
                        });
                        this.selectedIds = next;
                    } else {
                        this.selectedIds = this.selectedIds.filter((id) => !this.pageIds.includes(id));
                    }
                    this.persistSelected();
                },
                clearSelected() {
                    this.selectedIds = [];
                    this.persistSelected();
                },
                bulkIds() {
                    return this.selectedIds.slice(0, this.bulkLimit);
                },
                async bulkPublish() {
                    if (this.selectedCount === 0 || this.bulkBusy) return;
                    const ids = this.bulkIds();
                    const msg = ids.length < this.selectedCount
                        ? `Xuất bản tối đa ${this.bulkLimit} câu đầu? Chỉ câu 1 vòng + 2 cờ xanh được XB; còn lại bỏ qua (duyệt thủ công).`
                        : 'Xuất bản các câu đủ điều kiện (1 vòng + 2 cờ xanh)? Câu ≥2 vòng / có cờ đỏ / không chờ XB sẽ bị bỏ qua — duyệt thủ công trên form.';
                    if (!window.confirm(msg)) return;
                    await this.runBulk({ ids });
                },
                async runBulk(payload) {
                    this.bulkBusy = true;
                    try {
                        const res = await fetch(this.bulkUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify(payload),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            const firstError = data?.errors
                                ? Object.values(data.errors).flat()[0]
                                : (data?.message || 'Xuất bản hàng loạt thất bại.');
                            window.alert(firstError);
                            return;
                        }
                        window.alert(data.message || 'Đã xử lý.');
                        this.clearSelected();
                        window.location.reload();
                    } catch (e) {
                        window.alert('Không kết nối được máy chủ.');
                    } finally {
                        this.bulkBusy = false;
                    }
                },
                filterExportUrl(format) {
                    const params = new URLSearchParams();
                    Object.entries(this.exportQuery || {}).forEach(([key, value]) => {
                        if (key === 'ids' || key === 'format' || key === 'page' || value === null || value === '') {
                            return;
                        }
                        if (Array.isArray(value)) {
                            value.forEach((item) => params.append(`${key}[]`, item));
                        } else {
                            params.set(key, value);
                        }
                    });
                    if (format === 'csv') {
                        params.set('format', 'csv');
                    }
                    const query = params.toString();
                    return query ? `${this.exportUrl}?${query}` : this.exportUrl;
                },
                exportAs(format) {
                    if (this.selectedCount === 0) {
                        window.location = this.filterExportUrl(format);
                        return;
                    }
                    this.exportFormat = format;
                    this.$nextTick(() => this.$refs.exportForm.submit());
                },
                placePanel() {
                    const btn = this.$refs.columnTrigger;
                    if (!btn) return;
                    const rect = btn.getBoundingClientRect();
                    const panelWidth = 256;
                    const gap = 8;
                    let left = rect.right - panelWidth;
                    left = Math.max(12, Math.min(left, window.innerWidth - panelWidth - 12));
                    const top = Math.min(rect.bottom + gap, window.innerHeight - 12);
                    this.panelStyle = `top:${top}px;left:${left}px;`;
                },
                scrollTable(offset) {
                    const el = this.$refs.tableContainer;
                    if (!el) return;
                    el.scrollBy({ left: offset, behavior: 'smooth' });
                },
                updateScrollState() {
                    const el = this.$refs.tableContainer;
                    if (!el) return;
                    this.canScrollLeft = el.scrollLeft > 10;
                    this.canScrollRight = el.scrollLeft < (el.scrollWidth - el.clientWidth - 10);
                },
                async applyQuestionFilters() {
                    const form = document.getElementById('question-filter-form');
                    if (!form) return;

                    const url = new URL(form.action, window.location.origin);
                    const params = new URLSearchParams(new FormData(form));
                    params.delete('page');
                    url.search = params.toString();
                    await this.fetchQuestionResults(url.toString());
                },
                async resetQuestionFilters(url) {
                    const form = document.getElementById('question-filter-form');
                    if (form) {
                        const queryInput = form.querySelector('[name="q"]');
                        if (queryInput) queryInput.value = '';
                    }
                    window.dispatchEvent(new CustomEvent('question-filters-reset'));
                    await this.fetchQuestionResults(url);
                },
                async fetchQuestionResults(url) {
                    this.ajaxLoading = true;
                    try {
                        const response = await fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'text/html',
                            },
                        });
                        if (!response.ok) throw new Error('Lỗi tải danh sách câu hỏi');

                        const html = await response.text();
                        const documentHtml = new DOMParser().parseFromString(html, 'text/html');
                        const nextStats = documentHtml.getElementById('question-stats');
                        const nextResults = documentHtml.getElementById('question-results-region');
                        const currentStats = document.getElementById('question-stats');
                        const currentResults = document.getElementById('question-results-region');

                        if (!nextStats || !nextResults || !currentStats || !currentResults) {
                            throw new Error('Không tìm thấy vùng kết quả câu hỏi');
                        }

                        currentStats.replaceWith(nextStats);
                        currentResults.replaceWith(nextResults);
                        window.history.pushState({}, '', url);
                        this.$nextTick(() => {
                            this.bindQuestionPagination();
                            this.updateScrollState();
                        });
                    } catch (error) {
                        console.error(error);
                        alert('Có lỗi xảy ra khi tải danh sách câu hỏi. Vui lòng thử lại.');
                    } finally {
                        this.ajaxLoading = false;
                    }
                },
                bindQuestionPagination() {
                    const container = document.getElementById('question-results-region');
                    if (!container) return;
                    container.querySelectorAll('nav a').forEach((link) => {
                        link.addEventListener('click', (event) => {
                            event.preventDefault();
                            if (link.href) this.fetchQuestionResults(link.href);
                        });
                    });
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
                        this.updateScrollState();
                    });
                    window.addEventListener('scroll', () => {
                        if (this.open) {
                            this.placePanel();
                        }
                    }, true);
                    this.$nextTick(() => {
                        this.ajaxLoading = false;
                        this.bindQuestionPagination();
                        this.updateScrollState();
                    });
                },
            };
        }
    </script>
</x-layouts.admin>
