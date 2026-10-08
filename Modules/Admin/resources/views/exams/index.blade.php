@php
    $hasActiveFilters = filled($search) || filled($kind) || filled($status);
    $statCards = [
        ['label' => 'Tổng bài thi', 'value' => $stats['total'], 'icon' => 'quiz', 'tone' => 'bg-primary/10 text-primary'],
        ['label' => 'Bài mẫu', 'value' => $stats['sample'], 'icon' => 'description', 'tone' => 'bg-emerald-50 text-emerald-700'],
        ['label' => 'Đề cá nhân', 'value' => $stats['personal'], 'icon' => 'person', 'tone' => 'bg-indigo-50 text-indigo-700'],
        ['label' => 'Bản nháp', 'value' => $stats['draft'], 'icon' => 'draft', 'tone' => 'bg-amber-50 text-amber-700'],
    ];
@endphp

<x-layouts.admin title="Bài thi — Quản trị nội dung">
    <div x-data="examFilterForm({ hasActiveFilters: @js($hasActiveFilters) })" class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-headline-md text-headline-md font-bold tracking-tight text-on-surface">Bài thi</h1>
                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                    Theo dõi bài mẫu của kỳ thi và các đề cá nhân học viên đã tạo.
                </p>
            </div>
        </header>

        <x-admin.flash />

        <section id="exam-stats" aria-labelledby="heading-exam-stats">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 id="heading-exam-stats" class="font-label-lg font-semibold text-on-surface">Tổng quan</h2>
                <p class="text-sm text-on-surface-variant">Số liệu toàn bộ bài thi</p>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($statCards as $card)
                    <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-medium text-on-surface-variant">{{ $card['label'] }}</p>
                            <span @class(['flex size-10 shrink-0 items-center justify-center rounded-xl', $card['tone']])>
                                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">{{ $card['icon'] }}</span>
                            </span>
                        </div>
                        <p class="mt-2 text-3xl font-bold leading-none tabular-nums text-on-surface">{{ number_format($card['value']) }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="heading-exam-filters">
            <h2 id="heading-exam-filters" class="sr-only">Tìm kiếm bài thi</h2>
            <form method="get" action="{{ route('admin.exams.index') }}" id="exam-filter-form" role="search"
                aria-label="Tìm kiếm và lọc bài thi"
                @submit.prevent="applyExamFilters()"
                class="space-y-4 rounded-xl border border-outline-variant bg-surface p-4">
                <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(280px,1.5fr)_minmax(180px,1fr)_minmax(180px,1fr)]">
                    <div class="sm:col-span-2 xl:col-auto">
                        <label for="exam-search" class="mb-1.5 block text-sm font-medium text-on-surface-variant">Tìm kiếm</label>
                        <div class="relative">
                            <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant" aria-hidden="true">search</span>
                            <input id="exam-search" type="search" name="q" value="{{ $search }}"
                                autocomplete="off" placeholder="Tên bài, học viên hoặc ma trận..."
                                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 pl-9 text-sm text-on-surface outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>
                    </div>
                    <div class="min-w-0">
                        <label for="exam-kind" class="mb-1.5 block text-sm font-medium text-on-surface-variant">Loại bài</label>
                        <select id="exam-kind" name="kind" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">Tất cả</option>
                            <option value="sample" @selected($kind === 'sample')>Bài mẫu</option>
                            <option value="personal" @selected($kind === 'personal')>Đề cá nhân</option>
                            <option value="legacy" @selected($kind === 'legacy')>Đề cũ</option>
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label for="exam-status" class="mb-1.5 block text-sm font-medium text-on-surface-variant">Trạng thái</label>
                        <select id="exam-status" name="status" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">Tất cả</option>
                            <option value="published" @selected($status === 'published')>Đã xuất bản</option>
                            <option value="draft" @selected($status === 'draft')>Bản nháp</option>
                        </select>
                    </div>
                </div>

                <x-admin.filter-action-buttons class="justify-start border-t border-outline-variant pt-4" loading-expression="ajaxLoading"
                    reset-method="resetExamFilters" :reset-url="route('admin.exams.index')"
                    search-aria-label="Tìm kiếm bài thi" reset-aria-label="Xoá bộ lọc bài thi"
                    reset-label="Xoá bộ lọc" reset-icon="restart_alt" reset-title="Xoá bộ lọc"
                    show-reset-expression="hasAppliedFilters" reset-variant="text-danger" />
            </form>
        </section>

        <div id="exam-results-region">
        <section aria-labelledby="heading-exams-list" class="overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant px-5 py-4 sm:px-6">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h2 id="heading-exams-list" class="font-label-lg font-semibold text-on-surface">Danh sách bài thi</h2>
                    <span class="rounded-full bg-surface-container-low px-3 py-1 text-xs font-medium tabular-nums text-on-surface-variant">{{ number_format($exams->firstItem() ?? 0) }}–{{ number_format($exams->lastItem() ?? 0) }} / {{ number_format($exams->total()) }} bài</span>
                    @if ($exams->hasPages())
                        <span class="text-xs tabular-nums text-on-surface-variant">Trang {{ $exams->currentPage() }} / {{ $exams->lastPage() }}</span>
                    @endif
                </div>
                <span class="inline-flex items-center gap-1 text-xs text-on-surface-variant xl:hidden">
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">swap_horiz</span>
                    Vuốt ngang để xem bảng
                </span>
            </div>

            <div class="relative w-full overflow-x-auto" tabindex="0" aria-label="Vùng cuộn bảng dữ liệu bài thi">
                <table aria-label="Bảng danh sách bài thi" class="w-full min-w-[1180px] table-fixed border-collapse text-left font-body-sm text-on-surface">
                    <caption class="sr-only">Danh sách bài thi, học viên, ma trận, quy mô và trạng thái</caption>
                    <thead class="border-b border-outline-variant bg-surface-container-low text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                        <tr>
                            <th scope="col" class="w-[245px] px-5 py-3.5 sm:px-6">Bài thi</th>
                            <th scope="col" class="w-[175px] px-4 py-3.5">Học viên</th>
                            <th scope="col" class="w-[210px] px-4 py-3.5">Kỳ thi · ma trận</th>
                            <th scope="col" class="w-[90px] px-3 py-3.5 text-center">Số câu</th>
                            <th scope="col" class="w-[95px] px-3 py-3.5 text-center">Thời gian</th>
                            <th scope="col" class="w-[125px] px-4 py-3.5">Trạng thái</th>
                            <th scope="col" class="w-[105px] px-4 py-3.5">Tạo lúc</th>
                            <th scope="col" class="w-[135px] px-5 py-3.5 text-end sm:px-6">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        @forelse ($exams as $exam)
                            <tr class="transition-colors duration-150 hover:bg-surface-container-low/70 focus-within:bg-surface-container-low/70">
                                <td class="px-5 py-4 align-middle sm:px-6">
                                    <div class="mb-2 flex flex-wrap items-center gap-1.5">
                                        @if ($exam->kind === 'sample')
                                            <span class="inline-flex rounded-full border border-primary/20 bg-primary/5 px-2.5 py-0.5 text-xs font-semibold text-primary">Bài mẫu</span>
                                        @elseif ($exam->kind === 'personal')
                                            <span class="inline-flex rounded-full border border-outline-variant bg-surface-container-high px-2.5 py-0.5 text-xs font-semibold text-on-surface">Đề cá nhân</span>
                                        @else
                                            <span class="inline-flex rounded-full border border-outline-variant px-2.5 py-0.5 text-xs font-semibold text-on-surface-variant">Đề cũ</span>
                                        @endif
                                    </div>
                                    <p class="line-clamp-2 font-semibold leading-snug text-on-surface [text-wrap:pretty]" title="{{ $exam->title }}">{{ $exam->title }}</p>
                                    @if ($exam->description)
                                        <p class="mt-1 line-clamp-1 text-xs text-on-surface-variant" title="{{ $exam->description }}">{{ $exam->description }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-middle">
                                    @if ($exam->user)
                                        <p class="truncate font-medium text-on-surface" title="{{ $exam->user->name }}">{{ $exam->user->name }}</p>
                                        <p class="mt-1 truncate text-xs text-on-surface-variant" title="{{ $exam->user->email }}">{{ $exam->user->email }}</p>
                                    @else
                                        <span class="text-on-surface-variant">{{ $exam->kind === 'sample' ? 'Bài thi mẫu dùng chung' : 'Hệ thống' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-middle">
                                    @if ($exam->blueprint)
                                        <p class="line-clamp-2 font-medium text-on-surface" title="{{ $exam->blueprint->name }}">{{ $exam->blueprint->name }}</p>
                                        @if ($exam->blueprint->code)
                                            <p class="mt-1 truncate font-mono text-xs text-on-surface-variant" title="{{ $exam->blueprint->code }}">{{ $exam->blueprint->code }}</p>
                                        @endif
                                    @else
                                        <span class="text-on-surface-variant/60">Chưa gắn ma trận</span>
                                    @endif
                                </td>
                                <td class="px-3 py-4 text-center align-middle tabular-nums">
                                    <span class="font-semibold text-on-surface">{{ number_format($exam->questions_count) }}</span>
                                    <span class="block text-xs text-on-surface-variant">câu</span>
                                </td>
                                <td class="px-3 py-4 text-center align-middle tabular-nums">
                                    <span class="font-semibold text-on-surface">{{ number_format($exam->duration_minutes) }}</span>
                                    <span class="block text-xs text-on-surface-variant">phút</span>
                                </td>
                                <td class="px-4 py-4 align-middle whitespace-nowrap">
                                    <span @class([
                                        'inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold',
                                        'border-emerald-200 bg-emerald-50 text-emerald-800' => $exam->status->value === 'published',
                                        'border-amber-200 bg-amber-50 text-amber-800' => $exam->status->value !== 'published',
                                    ])>{{ $exam->status->label() }}</span>
                                </td>
                                <td class="px-4 py-4 align-middle tabular-nums whitespace-nowrap">
                                    <p>{{ $exam->created_at?->format('d/m/Y') }}</p>
                                    <p class="mt-1 text-xs text-on-surface-variant">{{ $exam->created_at?->format('H:i') }}</p>
                                </td>
                                <td class="px-5 py-4 text-end align-middle whitespace-nowrap sm:px-6">
                                    <div class="inline-flex items-center justify-end gap-1">
                                        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.exams.show'))
                                            <a href="{{ route('admin.exams.show', $exam) }}" class="inline-flex min-h-10 items-center justify-center gap-1 rounded-lg border border-outline-variant px-2.5 text-xs font-semibold text-primary transition-[background-color,border-color,transform] duration-150 hover:border-primary/30 hover:bg-primary/5 active:scale-[0.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40" title="Xem chi tiết bài thi" aria-label="Xem bài thi {{ $exam->title }}">
                                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                                                Xem
                                            </a>
                                        @endif
                                        @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.exams.destroy'))
                                            <form action="{{ route('admin.exams.destroy', $exam) }}" method="post" class="inline" onsubmit="return confirm('Xoá bài thi này?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex size-10 items-center justify-center rounded-lg text-error transition-[background-color,transform] duration-150 hover:bg-error/10 active:scale-[0.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-error/40" title="Xoá bài thi" aria-label="Xoá bài thi {{ $exam->title }}">
                                                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center">
                                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                                        <span class="material-symbols-outlined text-[28px]" aria-hidden="true">search_off</span>
                                    </div>
                                    <p class="mt-3 font-label-lg font-semibold text-on-surface">{{ $stats['total'] > 0 ? 'Không tìm thấy bài thi nào' : 'Chưa có bài thi nào' }}</p>
                                    <p class="mt-1 text-body-sm text-on-surface-variant">{{ $stats['total'] > 0 ? 'Thử điều chỉnh hoặc xoá bộ lọc tìm kiếm.' : 'Bài mẫu và đề học viên tạo sẽ xuất hiện tại đây.' }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($exams->hasPages())
            <nav aria-label="Điều hướng phân trang danh sách bài thi" class="pt-2">
                {{ $exams->links() }}
            </nav>
        @endif
        </div>
    </div>

    <script>
        function examFilterForm({ hasActiveFilters }) {
            return {
                ajaxLoading: false,
                hasAppliedFilters: Boolean(hasActiveFilters),
                hasCurrentExamFilters() {
                    const form = document.getElementById('exam-filter-form');

                    return form
                        ? [...new FormData(form).entries()].some(([key, value]) => key !== 'page' && String(value).trim() !== '')
                        : false;
                },
                async applyExamFilters() {
                    const form = document.getElementById('exam-filter-form');
                    if (!form) return;

                    const url = new URL(form.action, window.location.origin);
                    const params = new URLSearchParams(new FormData(form));
                    params.delete('page');
                    url.search = params.toString();
                    await this.fetchExamResults(url.toString());
                    this.hasAppliedFilters = this.hasCurrentExamFilters();
                },
                async resetExamFilters(url) {
                    const form = document.getElementById('exam-filter-form');
                    if (form) {
                        form.querySelectorAll('input[name], select[name]').forEach((field) => {
                            if (field.type === 'checkbox' || field.type === 'radio') {
                                field.checked = false;
                            } else {
                                field.value = '';
                            }
                        });
                    }

                    this.hasAppliedFilters = false;
                    await this.fetchExamResults(url);
                },
                async fetchExamResults(url) {
                    this.ajaxLoading = true;
                    try {
                        const response = await fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'text/html',
                            },
                        });
                        if (!response.ok) throw new Error('Lỗi tải danh sách bài thi');

                        const html = await response.text();
                        const documentHtml = new DOMParser().parseFromString(html, 'text/html');
                        const nextStats = documentHtml.getElementById('exam-stats');
                        const nextResults = documentHtml.getElementById('exam-results-region');
                        const currentStats = document.getElementById('exam-stats');
                        const currentResults = document.getElementById('exam-results-region');

                        if (!nextStats || !nextResults || !currentStats || !currentResults) {
                            throw new Error('Không tìm thấy vùng kết quả bài thi');
                        }

                        currentStats.replaceWith(nextStats);
                        currentResults.replaceWith(nextResults);
                        window.history.pushState({}, '', url);
                        this.$nextTick(() => this.bindExamPagination());
                    } catch (error) {
                        console.error(error);
                        alert('Có lỗi xảy ra khi tải danh sách bài thi. Vui lòng thử lại.');
                    } finally {
                        this.ajaxLoading = false;
                    }
                },
                bindExamPagination() {
                    const container = document.getElementById('exam-results-region');
                    if (!container) return;

                    container.querySelectorAll('nav a').forEach((link) => {
                        link.addEventListener('click', (event) => {
                            event.preventDefault();
                            if (link.href) this.fetchExamResults(link.href);
                        });
                    });
                },
                init() {
                    this.$nextTick(() => this.bindExamPagination());
                },
            };
        }
    </script>
</x-layouts.admin>
