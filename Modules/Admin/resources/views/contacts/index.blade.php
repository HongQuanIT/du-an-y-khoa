@php
    $statusTone = collect($statuses)->mapWithKeys(
        fn ($status) => [$status->value => $status->tone()]
    )->all();
@endphp

<x-layouts.admin title="Liên hệ">
    <x-admin.page-header title="Hộp thư liên hệ"
        description="Quản lý tin nhắn từ form /contact — lọc, gán xử lý và theo dõi trạng thái." />

    <x-admin.flash />

    <div x-data="adminContactFilter()" x-init="init()" class="space-y-6">
        <!-- Status filters -->
        <div class="grid overflow-hidden rounded-xl border border-outline-variant bg-surface sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5" role="group"
            aria-label="Lọc liên hệ theo trạng thái">
            @foreach ($statuses as $status)
                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.contacts.index'))
                    <button type="button" @click="filterByStatusKpi('{{ $status->value }}')"
                        :aria-pressed="statuses.includes('{{ $status->value }}')"
                        title="Tìm kiếm liên hệ {{ strtolower($status->label()) }}"
                        class="min-h-32 border-b border-outline-variant p-4 text-left transition hover:bg-primary/5 focus:z-10 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 sm:border-r last:border-r-0 lg:border-b-0"
                        :class="statuses.includes('{{ $status->value }}') ? 'bg-primary/5 text-primary' : 'text-on-surface'">
                        <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ $status->label() }}</p>
                        <p id="kpi-count-{{ $status->value }}" class="mt-2 text-2xl font-bold text-on-surface">{{ number_format((int) ($statusCounts[$status->value] ?? 0)) }}</p>
                    </button>
                @endif
            @endforeach
        </div>

        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.contacts.index'))
            <!-- Filter Bar -->
            <form @submit.prevent="applyFilter()" role="search" aria-label="Tìm kiếm liên hệ"
                class="grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 md:grid-cols-12">
                
                <!-- Search Keyword -->
                <div class="md:col-span-3">
                    <label for="contact-search-q" class="mb-1.5 block text-sm font-medium text-on-surface-variant">Tìm kiếm</label>
                    <div class="relative">
                        <input id="contact-search-q" x-model="q" type="search"
                            placeholder="Mã, tên, email, nội dung…"
                            class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 pl-9 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                        <span class="material-symbols-outlined pointer-events-none absolute top-2.5 left-2.5 text-[20px] text-on-surface-variant/70">search</span>
                    </div>
                </div>

                <!-- Multi-select: Trạng thái -->
                <div class="relative md:col-span-2" @click.outside="statusOpen = false">
                    <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Trạng thái</span>
                    <button type="button" @click="statusOpen = !statusOpen; subjectOpen = false; assignedOpen = false"
                        id="contact-filter-status-trigger"
                        aria-haspopup="true"
                        :aria-expanded="statusOpen"
                        aria-label="Lọc theo trạng thái"
                        class="flex h-11 w-full items-center justify-between rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface hover:border-outline focus:border-primary focus:ring-1 focus:ring-primary">
                        <span class="truncate" x-text="statusLabel()"></span>
                        <div class="flex items-center gap-1">
                            <template x-if="statuses.length > 0">
                                <span class="flex size-5 items-center justify-center rounded-full bg-primary text-xs font-bold text-on-primary" x-text="statuses.length"></span>
                            </template>
                            <span class="material-symbols-outlined text-[20px] text-on-surface-variant transition-transform duration-200" :class="statusOpen ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
                        </div>
                    </button>

                    <!-- Dropdown Menu -->
                    <div x-show="statusOpen" x-cloak x-transition.origin.top.duration.150ms
                        class="absolute left-0 z-30 mt-1.5 max-h-72 w-64 overflow-y-auto rounded-xl border border-outline-variant bg-surface p-2 shadow-xl">
                        <div class="mb-1.5 flex items-center justify-between border-b border-outline-variant px-2 pb-1.5 text-xs">
                            <span class="font-semibold text-on-surface-variant">Chọn trạng thái</span>
                            <button type="button" @click="statuses = []" class="text-xs text-primary hover:underline" x-show="statuses.length > 0">Bỏ chọn</button>
                        </div>
                        <div class="space-y-1">
                            @foreach ($statuses as $status)
                                <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-sm hover:bg-surface-container-low">
                                    <input type="checkbox" value="{{ $status->value }}" x-model="statuses"
                                        class="size-4 rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $status->tone() }}">
                                        {{ $status->label() }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Multi-select: Chủ đề -->
                <div class="relative md:col-span-2" @click.outside="subjectOpen = false">
                    <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Chủ đề</span>
                    <button type="button" @click="subjectOpen = !subjectOpen; statusOpen = false; assignedOpen = false"
                        id="contact-filter-subject-trigger"
                        aria-haspopup="true"
                        :aria-expanded="subjectOpen"
                        aria-label="Lọc theo chủ đề"
                        class="flex h-11 w-full items-center justify-between rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface hover:border-outline focus:border-primary focus:ring-1 focus:ring-primary">
                        <span class="truncate" x-text="subjectLabel()"></span>
                        <div class="flex items-center gap-1">
                            <template x-if="subjects.length > 0">
                                <span class="flex size-5 items-center justify-center rounded-full bg-primary text-xs font-bold text-on-primary" x-text="subjects.length"></span>
                            </template>
                            <span class="material-symbols-outlined text-[20px] text-on-surface-variant transition-transform duration-200" :class="subjectOpen ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
                        </div>
                    </button>

                    <!-- Dropdown Menu -->
                    <div x-show="subjectOpen" x-cloak x-transition.origin.top.duration.150ms
                        class="absolute left-0 z-30 mt-1.5 max-h-72 w-72 overflow-y-auto rounded-xl border border-outline-variant bg-surface p-2 shadow-xl">
                        <div class="mb-1.5 flex items-center justify-between border-b border-outline-variant px-2 pb-1.5 text-xs">
                            <span class="font-semibold text-on-surface-variant">Chọn chủ đề</span>
                            <button type="button" @click="subjects = []" class="text-xs text-primary hover:underline" x-show="subjects.length > 0">Bỏ chọn</button>
                        </div>
                        <div class="space-y-1">
                            @foreach ($subjects as $subject)
                                <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-sm hover:bg-surface-container-low">
                                    <input type="checkbox" value="{{ $subject->value }}" x-model="subjects"
                                        class="size-4 rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="material-symbols-outlined text-[16px] text-primary">{{ $subject->icon() }}</span>
                                    <span class="text-on-surface">{{ $subject->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Multi-select: Phân công -->
                <div class="relative md:col-span-2" @click.outside="assignedOpen = false">
                    <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Phân công</span>
                    <button type="button" @click="assignedOpen = !assignedOpen; statusOpen = false; subjectOpen = false"
                        id="contact-filter-assigned-trigger"
                        aria-haspopup="true"
                        :aria-expanded="assignedOpen"
                        aria-label="Lọc theo người được phân công"
                        class="flex h-11 w-full items-center justify-between rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface hover:border-outline focus:border-primary focus:ring-1 focus:ring-primary">
                        <span class="truncate" x-text="assignedLabel()"></span>
                        <div class="flex items-center gap-1">
                            <template x-if="assigned.length > 0">
                                <span class="flex size-5 items-center justify-center rounded-full bg-primary text-xs font-bold text-on-primary" x-text="assigned.length"></span>
                            </template>
                            <span class="material-symbols-outlined text-[20px] text-on-surface-variant transition-transform duration-200" :class="assignedOpen ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
                        </div>
                    </button>

                    <!-- Dropdown Menu -->
                    <div x-show="assignedOpen" x-cloak x-transition.origin.top.duration.150ms
                        class="absolute left-0 z-30 mt-1.5 w-60 rounded-xl border border-outline-variant bg-surface p-2 shadow-xl">
                        <div class="mb-1.5 flex items-center justify-between border-b border-outline-variant px-2 pb-1.5 text-xs">
                            <span class="font-semibold text-on-surface-variant">Chọn phân công</span>
                            <button type="button" @click="assigned = []" class="text-xs text-primary hover:underline" x-show="assigned.length > 0">Bỏ chọn</button>
                        </div>
                        <div class="space-y-1">
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-sm hover:bg-surface-container-low">
                                <input type="checkbox" value="unassigned" x-model="assigned"
                                    class="size-4 rounded border-outline-variant text-primary focus:ring-primary">
                                <span class="text-on-surface">Chưa gán</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-sm hover:bg-surface-container-low">
                                <input type="checkbox" value="me" x-model="assigned"
                                    class="size-4 rounded border-outline-variant text-primary focus:ring-primary">
                                <span class="text-on-surface">Của tôi</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons: Tìm kiếm & Xóa lọc -->
                <div class="flex items-center gap-2 md:col-span-3">
                    <button type="submit" id="btn-contacts-apply-filter" :disabled="loading"
                        title="Tìm kiếm liên hệ"
                        aria-label="Tìm kiếm liên hệ"
                        class="inline-flex h-11 flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-primary px-3 text-sm font-semibold text-on-primary shadow-xs transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:opacity-50">
                        <template x-if="loading">
                            <span class="size-4 animate-spin rounded-full border-2 border-on-primary border-t-transparent" aria-hidden="true"></span>
                        </template>
                        <template x-if="!loading">
                            <span class="material-symbols-outlined text-[18px] shrink-0" aria-hidden="true">search</span>
                        </template>
                        <span class="whitespace-nowrap">Tìm kiếm</span>
                    </button>
                    <button type="button" id="btn-contacts-reset-filter" @click="resetFilter()" :disabled="loading"
                        title="Xoá"
                        aria-label="Xoá"
                        class="inline-flex h-11 flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-outline-variant bg-surface px-3 text-sm font-semibold text-on-surface-variant transition hover:bg-surface-container-low focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50">
                        <span class="material-symbols-outlined text-[18px] shrink-0" aria-hidden="true">delete</span>
                        <span class="whitespace-nowrap">Xoá</span>
                    </button>
                </div>
            </form>
        @endif

        <!-- Table Container with AJAX update & loading indicator -->
        <div class="relative overflow-hidden rounded-xl border border-outline-variant bg-surface">
            <!-- Loading overlay -->
            <div x-show="loading" x-cloak
                class="absolute inset-0 z-20 flex items-center justify-center bg-surface/60 backdrop-blur-xs transition">
                <div class="flex items-center gap-2 rounded-lg bg-surface px-4 py-2 shadow-md border border-outline-variant">
                    <span class="size-5 animate-spin rounded-full border-2 border-primary border-t-transparent"></span>
                    <span class="text-sm font-medium text-on-surface">Đang tải dữ liệu…</span>
                </div>
            </div>

            <!-- Partial Table Content -->
            <div id="contacts-table-container">
                @include('admin::contacts.partials.table', [
                    'inquiries' => $inquiries,
                    'statusTone' => $statusTone,
                ])
            </div>
        </div>
    </div>

    <script>
        function adminContactFilter() {
            return {
                q: @js($filters['q']),
                statuses: @js($filters['status']),
                subjects: @js($filters['subject']),
                assigned: @js($filters['assigned']),
                statusOpen: false,
                subjectOpen: false,
                assignedOpen: false,
                loading: false,

                statusLabel() {
                    if (this.statuses.length === 0) return 'Tất cả';
                    if (this.statuses.length === 1) {
                        const map = @js(collect($statuses)->mapWithKeys(fn($s) => [$s->value => $s->label()])->all());
                        return map[this.statuses[0]] || this.statuses[0];
                    }
                    return this.statuses.length + ' đã chọn';
                },

                subjectLabel() {
                    if (this.subjects.length === 0) return 'Tất cả';
                    if (this.subjects.length === 1) {
                        const map = @js(collect($subjects)->mapWithKeys(fn($s) => [$s->value => $s->label()])->all());
                        return map[this.subjects[0]] || this.subjects[0];
                    }
                    return this.subjects.length + ' đã chọn';
                },

                assignedLabel() {
                    if (this.assigned.length === 0) return 'Tất cả';
                    const labels = [];
                    if (this.assigned.includes('unassigned')) labels.push('Chưa gán');
                    if (this.assigned.includes('me')) labels.push('Của tôi');
                    return labels.length === 1 ? labels[0] : labels.length + ' đã chọn';
                },

                buildQueryUrl(baseUrl = @js(route('admin.contacts.index'))) {
                    const url = new URL(baseUrl, window.location.origin);
                    if (this.q && this.q.trim() !== '') {
                        url.searchParams.set('q', this.q.trim());
                    }
                    this.statuses.forEach(s => url.searchParams.append('status[]', s));
                    this.subjects.forEach(s => url.searchParams.append('subject[]', s));
                    this.assigned.forEach(a => url.searchParams.append('assigned[]', a));
                    return url.toString();
                },

                async fetchContacts(url) {
                    this.loading = true;
                    try {
                        const response = await fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            }
                        });
                        if (!response.ok) throw new Error('Lỗi tải dữ liệu');
                        const data = await response.json();
                        
                        const container = document.getElementById('contacts-table-container');
                        if (container && data.table_html) {
                            container.innerHTML = data.table_html;
                            this.bindPagination();
                        }

                        if (data.statusCounts) {
                            for (const [key, count] of Object.entries(data.statusCounts)) {
                                const el = document.getElementById('kpi-count-' + key);
                                if (el) el.textContent = Number(count).toLocaleString();
                            }
                        }
                        if (data.openCount !== undefined) {
                            const el = document.getElementById('kpi-count-open');
                            if (el) el.textContent = Number(data.openCount).toLocaleString();
                        }

                        window.history.pushState({}, '', url);
                    } catch (err) {
                        console.error(err);
                        alert('Có lỗi xảy ra khi lọc dữ liệu. Vui lòng thử lại.');
                    } finally {
                        this.loading = false;
                    }
                },

                applyFilter() {
                    this.statusOpen = false;
                    this.subjectOpen = false;
                    this.assignedOpen = false;
                    const url = this.buildQueryUrl();
                    this.fetchContacts(url);
                },

                resetFilter() {
                    this.q = '';
                    this.statuses = [];
                    this.subjects = [];
                    this.assigned = [];
                    this.statusOpen = false;
                    this.subjectOpen = false;
                    this.assignedOpen = false;
                    const url = @js(route('admin.contacts.index'));
                    this.fetchContacts(url);
                },

                filterByStatusKpi(statusVal) {
                    if (statusVal === '') {
                        this.statuses = [];
                    } else {
                        this.statuses = [statusVal];
                    }
                    this.applyFilter();
                },

                bindPagination() {
                    const container = document.getElementById('contacts-table-container');
                    if (!container) return;
                    const links = container.querySelectorAll('.pagination-container a');
                    links.forEach(link => {
                        link.addEventListener('click', (e) => {
                            e.preventDefault();
                            if (link.href) {
                                this.fetchContacts(link.href);
                            }
                        });
                    });
                },

                init() {
                    this.bindPagination();
                }
            };
        }
    </script>
</x-layouts.admin>
