@php
    // Static port of html/pc-custom-session.html. Placeholders until filter builder lands.
    $topicFilters = [
        [
            'label' => 'Kỳ thi',
            'chips' => ['USMLE Step 2 CK', '+1'],
            'fallback' => null,
        ],
        [
            'label' => 'Bài viết',
            'chips' => ['ABCDE approach'],
            'fallback' => null,
        ],
        [
            'label' => 'Hệ cơ quan',
            'chips' => [],
            'fallback' => 'Tất cả',
            'openSystems' => true,
        ],
        [
            'label' => 'Chuyên khoa',
            'chips' => [],
            'fallback' => 'Tất cả',
        ],
        [
            'label' => 'Triệu chứng',
            'chips' => [],
            'fallback' => 'Tất cả',
        ],
        [
            'label' => 'Câu hỏi đã lưu',
            'chips' => [],
            'fallback' => 'Tất cả',
            'last' => true,
        ],
    ];

    $criteria = [
        [
            'label' => 'Độ khó',
            'chip' => 'Rất khó',
            'chipClass' => 'bg-error-container text-on-error-container',
        ],
        [
            'label' => 'Trạng thái',
            'chip' => 'Chưa trả lời',
            'chipClass' => 'bg-secondary-fixed text-on-secondary-fixed',
            'last' => true,
        ],
    ];

    $systems = [
        'Behavioral Health',
        'Biostatistics & Epidemiology',
        'Blood & Lymphoreticular Systems',
        'Cardiovascular System',
        'Endocrine System',
        'Female and Transgender Reproductive System & Breast',
        'Gastrointestinal System',
        'Human Development',
    ];
@endphp

<x-layouts.app title="Tạo phiên luyện tập">
    <div class="flex min-h-[calc(100vh-var(--spacing-header-height))] flex-col"
        x-data="{
            adaptive: false,
            mode: 'study',
            count: 0,
            systemsOpen: false,
            get canStart() { return this.count > 0 },
        }">
        <div class="mx-auto w-full max-w-[1440px] flex-1 overflow-y-auto p-8 pb-24">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <nav class="flex items-center gap-2 text-label-md text-on-surface-variant">
                    <a href="{{ route('qbank.index') }}"
                        class="cursor-pointer transition-colors hover:text-primary">Ngân hàng câu hỏi</a>
                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    <span class="font-bold text-primary">Tạo phiên luyện tập</span>
                </nav>
                <button type="button"
                    class="flex items-center gap-2 text-sm font-bold tracking-wider text-on-surface-variant uppercase transition-colors hover:text-primary">
                    <span class="material-symbols-outlined text-[20px]">refresh</span>
                    Đặt lại
                </button>
            </div>

            <!-- AI Session Shortcut -->
            <div class="mb-8 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                <div class="flex items-center gap-2 font-bold text-primary">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                    <span>Tạo phiên luyện tập bằng chế độ AI</span>
                </div>
                <button type="button"
                    class="flex items-center gap-2 rounded-full border border-outline-variant bg-white px-4 py-2 text-sm font-medium hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[18px] text-primary">upload_file</span>
                    Tải tệp lên để tạo phiên luyện
                </button>
                <button type="button"
                    class="flex items-center gap-2 rounded-full border border-outline-variant bg-white px-4 py-2 text-sm font-medium hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[18px] text-primary">chat_bubble_outline</span>
                    Mô tả nội dung bạn muốn học
                </button>
            </div>

            <div class="grid grid-cols-12 gap-8">
                <!-- Left: Topics -->
                <div class="col-span-12 lg:col-span-7">
                    <div class="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
                        <div class="border-b border-outline-variant p-6">
                            <h2 class="font-headline-sm text-headline-sm text-on-surface">Thiết lập chủ đề</h2>
                        </div>
                        <div class="space-y-6 p-6">
                            <div>
                                <p class="mb-3 text-[11px] font-bold tracking-widest text-on-surface-variant uppercase">
                                    Tìm kiếm bộ lọc</p>
                                <div class="relative">
                                    <span
                                        class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
                                    <input type="text"
                                        class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm placeholder:italic focus:ring-2 focus:ring-primary"
                                        placeholder="Ví dụ: bài viết, hệ thống, chuyên khoa">
                                </div>
                            </div>
                            <div class="-mx-6 space-y-0 border-t border-outline-variant">
                                @foreach ($topicFilters as $filter)
                                    <button type="button"
                                        @if (!empty($filter['openSystems'])) @click="systemsOpen = true" @endif
                                        @class([
                                            'group flex w-full cursor-pointer items-center justify-between px-6 py-4 text-left transition-colors hover:bg-surface-container-lowest',
                                            'border-b border-outline-variant' => empty($filter['last']),
                                        ])>
                                        <div class="flex items-center gap-4">
                                            <span
                                                class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                            <span class="font-medium">{{ $filter['label'] }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @forelse ($filter['chips'] as $chip)
                                                <span
                                                    class="rounded bg-secondary-fixed px-3 py-1 text-[12px] font-medium text-on-secondary-fixed">{{ $chip }}</span>
                                            @empty
                                                @if ($filter['fallback'])
                                                    <span
                                                        class="text-sm text-on-surface-variant">{{ $filter['fallback'] }}</span>
                                                @endif
                                            @endforelse
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Criteria -->
                <div class="col-span-12 lg:col-span-5">
                    <div class="overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm">
                        <div class="border-b border-outline-variant p-6">
                            <h2 class="font-headline-sm text-headline-sm text-on-surface">Tiêu chí phiên luyện</h2>
                        </div>
                        <div class="space-y-8 p-6">
                            <div>
                                <p class="mb-3 text-[11px] font-bold tracking-widest text-on-surface-variant uppercase">
                                    Tên phiên luyện</p>
                                <input type="text" value="Phiên tùy chỉnh từ 27 thg 7, 8:00 AM"
                                    class="w-full rounded-lg border border-outline-variant bg-white px-4 py-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary">
                            </div>

                            <div
                                class="flex items-start justify-between rounded-xl border border-outline-variant bg-surface-container-low p-4">
                                <div class="flex gap-3">
                                    <span class="material-symbols-outlined mt-0.5 text-primary"
                                        style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                                    <div>
                                        <p class="text-sm font-bold text-primary">Phiên luyện thích ứng</p>
                                        <p class="mt-1 text-xs leading-relaxed text-on-surface-variant">
                                            Câu hỏi được ưu tiên theo mức độ ảnh hưởng
                                            <a href="#" class="font-medium text-primary underline">Cách hoạt động</a>
                                        </p>
                                    </div>
                                </div>
                                <button type="button" @click="adaptive = !adaptive"
                                    class="relative flex h-6 w-12 items-center rounded-full transition-all"
                                    :class="adaptive ? 'bg-primary' : 'bg-outline-variant'"
                                    :aria-pressed="adaptive.toString()">
                                    <span class="absolute size-4 rounded-full bg-white transition-all"
                                        :class="adaptive ? 'left-7' : 'left-1'"></span>
                                </button>
                            </div>

                            <div class="-mx-6 space-y-0 border-y border-outline-variant">
                                @foreach ($criteria as $item)
                                    <button type="button"
                                        @class([
                                            'group flex w-full cursor-pointer items-center justify-between px-6 py-4 text-left hover:bg-surface-container-lowest',
                                            'border-b border-outline-variant' => empty($item['last']),
                                        ])>
                                        <div class="flex items-center gap-4">
                                            <span
                                                class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">add</span>
                                            <span class="font-medium">{{ $item['label'] }}</span>
                                        </div>
                                        <span
                                            class="rounded px-3 py-1 text-[12px] font-medium {{ $item['chipClass'] }}">{{ $item['chip'] }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <button type="button"
                                class="flex items-center gap-1 text-[11px] font-bold tracking-widest text-on-surface-variant uppercase hover:text-primary">
                                Thêm <span class="material-symbols-outlined text-[16px]">expand_more</span>
                            </button>

                            <div>
                                <p class="mb-3 text-[11px] font-bold tracking-widest text-on-surface-variant uppercase">
                                    Số lượng câu hỏi</p>
                                <div class="flex items-center gap-3">
                                    <input type="number" min="0" x-model.number="count"
                                        class="w-20 rounded-lg border border-outline-variant py-2.5 text-center text-lg font-bold focus:ring-2 focus:ring-primary">
                                    <span class="text-lg font-medium text-on-surface-variant">/ 0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Action Bar -->
        <div
            class="fixed right-0 bottom-0 left-0 z-40 flex items-center justify-center gap-8 border-t border-outline-variant bg-white p-4 md:left-sidebar-width">
            <div class="flex flex-wrap items-center justify-center gap-4">
                <span class="text-[11px] font-bold tracking-widest text-on-surface-variant uppercase">Chế độ</span>
                <div class="flex rounded-lg border border-outline-variant bg-surface-container-low p-1">
                    <button type="button" @click="mode = 'study'"
                        class="rounded-lg px-6 py-2 text-sm font-bold transition-all"
                        :class="mode === 'study'
                            ? 'bg-white text-primary border border-primary shadow-sm'
                            : 'text-on-surface-variant hover:text-on-surface'">
                        Chế độ học tập
                    </button>
                    <button type="button" @click="mode = 'exam'"
                        class="rounded-lg px-6 py-2 text-sm font-bold transition-all"
                        :class="mode === 'exam'
                            ? 'bg-white text-primary border border-primary shadow-sm'
                            : 'text-on-surface-variant hover:text-on-surface'">
                        Chế độ thi
                    </button>
                </div>
            </div>
            <a :href="canStart
                    ? (mode === 'exam' ? '{{ route('qbank.exam') }}' : '{{ route('qbank.session') }}')
                    : '#'"
                @click="if (!canStart) $event.preventDefault()"
                class="rounded-lg px-12 py-2.5 font-bold text-white transition-all"
                :class="canStart
                    ? 'bg-primary shadow-md hover:bg-primary/90 cursor-pointer'
                    : 'bg-primary/30 cursor-not-allowed opacity-70'"
                :aria-disabled="!canStart">
                Bắt đầu
            </a>
        </div>

        <!-- Systems Modal -->
        <div x-show="systemsOpen" x-cloak x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @keydown.escape.window="systemsOpen = false">
            <div class="flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-xl bg-white shadow-xl"
                @click.outside="systemsOpen = false">
                <div class="flex items-center justify-between border-b border-outline-variant p-4">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">Systems</h3>
                    <button type="button" @click="systemsOpen = false"
                        class="rounded-full p-2 transition-colors hover:bg-surface-container">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="custom-scrollbar space-y-4 overflow-y-auto p-4">
                    <div class="relative">
                        <span
                            class="material-symbols-outlined absolute top-1/2 left-3 -translate-y-1/2 text-[20px] text-on-surface-variant">search</span>
                        <input type="text" placeholder="Search systems..."
                            class="w-full rounded-lg border-none bg-surface-container-low py-2.5 pr-4 pl-10 text-sm focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="flex items-start gap-3 rounded-lg bg-surface-container-low p-3">
                        <div class="mt-1">
                            <input type="checkbox" checked
                                class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                        </div>
                        <div>
                            <p class="text-sm font-bold">All</p>
                            <p class="text-xs leading-relaxed text-on-surface-variant">
                                By default, all systems are included unless filters are selected.
                            </p>
                        </div>
                    </div>
                    <div class="space-y-1">
                        @foreach ($systems as $system)
                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-surface-container-low">
                                <input type="checkbox"
                                    class="size-5 rounded border-outline-variant text-primary focus:ring-primary">
                                <span class="text-sm">{{ $system }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div
                    class="flex items-center justify-between border-t border-outline-variant bg-surface-container-lowest p-4">
                    <button type="button" class="text-sm font-bold text-primary hover:underline"
                        @click="systemsOpen = false">Reset</button>
                    <button type="button"
                        class="rounded-lg bg-primary px-8 py-2 font-bold text-white transition-opacity hover:opacity-90"
                        @click="systemsOpen = false">Done</button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
