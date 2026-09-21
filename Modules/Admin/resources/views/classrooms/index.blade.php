<x-layouts.admin title="Lớp học">
    <div x-data="adminClassroomFilter()" class="space-y-6">
    <x-admin.page-header title="Lớp học (giám sát)"
        description="Duyệt lớp giảng viên, xem live đang dạy, force-end hoặc lưu trữ khi cần." />

    <x-admin.flash />

    <div class="mb-6 flex justify-end">
        @can('classroom_oversight.create_on_behalf')
            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.classrooms.create'))
<a href="{{ route('admin.classrooms.create') }}"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-label-md text-label-md font-semibold text-on-primary hover:opacity-90">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Tạo lớp và nội dung
            </a>
@endif
        @endcan
    </div>

    @if ($pendingCount > 0)
        <div class="mb-6 flex flex-col gap-3 rounded-xl border border-tertiary/30 bg-tertiary/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="font-body-sm text-body-sm text-on-surface">
                <span class="font-semibold">{{ $pendingCount }}</span> lớp đang chờ duyệt trước khi hiển thị cho học viên.
            </p>
            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.classrooms.index'))
<a href="{{ route('admin.classrooms.index', ['status' => \Modules\Classroom\Enums\ClassroomStatus::PendingApproval->value]) }}"
                class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 font-label-md text-label-md text-on-primary hover:opacity-90">
                Xem chờ duyệt
            </a>
@endif
        </div>
    @endif

    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.classrooms.index'))
<form method="get" action="{{ route('admin.classrooms.index') }}" role="search"
        aria-labelledby="classroom-filter-heading" aria-describedby="classroom-filter-description"
        @submit.prevent="applyFilters()"
        class="mb-6 space-y-4 rounded-xl border border-outline-variant bg-surface p-4">
        <div>
            <h2 id="classroom-filter-heading" class="font-label-lg font-semibold text-on-surface">Tìm kiếm lớp học</h2>
            <p id="classroom-filter-description" class="mt-1 font-body-sm text-on-surface-variant">Tìm theo tên lớp, mã tham gia, UUID hoặc tên giảng viên; sau đó có thể thu hẹp theo các bộ lọc.</p>
        </div>
        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(280px,1.5fr)_repeat(3,minmax(150px,1fr))_auto]">
            <div class="sm:col-span-2 xl:col-auto">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="q">Tìm kiếm</label>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-on-surface-variant" aria-hidden="true">search</span>
                    <input id="q" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Tên lớp, mã tham gia hoặc giảng viên" autocomplete="off"
                        class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low py-2 pl-10 pr-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
            </div>
            <div class="min-w-0">
                <x-admin.multi-select-filter name="status" label="Trạng thái" :options="collect($statuses)->map(fn ($item) => ['id' => $item->value, 'label' => $item->label()])->all()" :selected="$filters['status']" />
            </div>
            <div class="min-w-0">
                <x-admin.multi-select-filter name="purpose" label="Mục đích" :options="collect($purposes)->map(fn ($item) => ['id' => $item->value, 'label' => $item->label()])->all()" :selected="$filters['purpose']" />
            </div>
            <div class="min-w-0">
                <x-admin.multi-select-filter name="host_id" label="Giảng viên" :options="$hosts->map(fn ($host) => ['id' => $host->id, 'label' => $host->name])->all()" :selected="$filters['host_id']" />
            </div>
            <div class="flex self-end gap-2 sm:col-span-2 xl:col-auto">
                <button type="submit" :disabled="loading" aria-label="Tìm kiếm lớp học"
                class="inline-flex h-11 w-36 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 font-label-md font-medium text-on-primary transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-primary/40 disabled:opacity-50">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true" x-text="loading ? 'progress_activity' : 'search'">search</span>
                    <span class="whitespace-nowrap" x-text="loading ? 'Đang tải' : 'Tìm kiếm'">Tìm kiếm</span>
                </button>
                <button type="button" @click="resetFilters(@js(route('admin.classrooms.index')))" :disabled="loading" aria-label="Xoá bộ lọc lớp học"
                class="inline-flex h-11 w-28 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-3 font-label-md font-medium text-on-surface-variant transition hover:bg-surface-container-low focus-visible:ring-2 focus-visible:ring-primary/20 disabled:opacity-50">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span><span>Xoá</span>
                </button>
            </div>
        </div>
    </form>
@endif

    <div id="classrooms-results-region">
    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Lớp</th>
                    <th class="px-4 py-3">Giảng viên</th>
                    <th class="px-4 py-3">Mục đích</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">TV</th>
                    <th class="px-4 py-3">Trực tiếp</th>
                    <th class="px-4 py-3 text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classrooms as $classroom)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3">
                            <div class="font-label-md text-label-md text-on-surface">{{ $classroom->title }}</div>
                            <div class="font-label-sm text-label-sm text-on-surface-variant">
                                {{ $classroom->join_code ?? '—' }} · {{ $classroom->uuid }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ $classroom->host?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ match ($classroom->meta['content_source'] ?? null) {
                                'questions' => 'Chữa câu hỏi',
                                'exam' => 'Chữa đề thi',
                                'feedback' => 'Chữa từ feedback',
                                default => $classroom->purpose?->label() ?? '—',
                            } }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($classroom->status === \Modules\Classroom\Enums\ClassroomStatus::PendingApproval)
                                <span class="font-label-sm text-label-sm font-semibold text-tertiary">{{ $classroom->status->label() }}</span>
                            @else
                                <span class="text-on-surface-variant">{{ $classroom->status->label() }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            {{ $classroom->active_members_count }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($classroom->liveSession)
                                <div class="space-y-1">
                                    <span class="font-label-sm text-label-sm font-semibold text-error">ĐANG TRỰC TIẾP</span>
                                    <div class="text-xs text-on-surface-variant">
                                        {{ $classroom->liveSession->title }}
                                    </div>
                                    <div class="text-xs text-on-surface-variant">
                                        GV: {{ $classroom->host?->name ?? '—' }}
                                    </div>
                                </div>
                            @else
                                <span class="text-on-surface-variant">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.classrooms.show'))
<a href="{{ route('admin.classrooms.show', $classroom) }}"
                                    class="rounded-lg bg-primary px-3 py-1.5 font-label-sm text-label-sm font-semibold text-on-primary hover:opacity-90">
                                    Vào lớp
                                </a>
@endif
                                @if ($classroom->liveSession)
                                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.classrooms.live'))
<a href="{{ route('admin.classrooms.live', [$classroom, $classroom->liveSession]) }}"
                                        class="rounded-lg bg-error px-3 py-1.5 font-label-sm text-label-sm font-semibold text-white hover:opacity-90">
                                        Xem live
                                    </a>
@endif
                                @endif
                                @if ($classroom->status === \Modules\Classroom\Enums\ClassroomStatus::PendingApproval)
                                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.classrooms.approve'))
<form method="post" action="{{ route('admin.classrooms.approve', $classroom) }}">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-lg bg-primary px-3 py-1.5 font-label-sm text-label-sm font-semibold text-on-primary hover:opacity-90">
                                            Duyệt
                                        </button>
                                    </form>
@endif
                                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.classrooms.reject'))
<form method="post" action="{{ route('admin.classrooms.reject', $classroom) }}"
                                        onsubmit="return confirm('Từ chối lớp này?')">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm text-label-sm text-on-surface hover:bg-surface-container-low">
                                            Từ chối
                                        </button>
                                    </form>
@endif
                                @endif
                                @if ($classroom->live_sessions_count > 0)
                                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.classrooms.force-end'))
<form method="post" action="{{ route('admin.classrooms.force-end', $classroom) }}"
                                        onsubmit="return confirm('Force-end buổi live của lớp này?')">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm text-label-sm text-on-surface hover:bg-surface-container-low">
                                            Force-end
                                        </button>
                                    </form>
@endif
                                @endif
                                @if ($classroom->status !== \Modules\Classroom\Enums\ClassroomStatus::Archived)
                                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.classrooms.archive'))
<form method="post" action="{{ route('admin.classrooms.archive', $classroom) }}"
                                        onsubmit="return confirm('Lưu trữ lớp này?')">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm text-label-sm text-on-surface hover:bg-surface-container-low">
                                            Lưu trữ
                                        </button>
                                    </form>
@endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-on-surface-variant">Không có lớp khớp bộ lọc.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4" id="classrooms-pagination">
        {{ $classrooms->links() }}
    </div>
    </div>

    <script>
        function adminClassroomFilter() {
            return {
                loading: false,
                filterForm() { return document.querySelector('form[role="search"]'); },
                async applyFilters() {
                    const form = this.filterForm();
                    if (!form) return;
                    const url = new URL(form.action, window.location.origin);
                    const params = new URLSearchParams(new FormData(form));
                    params.delete('page');
                    url.search = params.toString();
                    await this.fetchResults(url.toString());
                },
                async resetFilters(url) {
                    const form = this.filterForm();
                    form?.reset();
                    const query = form?.querySelector('[name="q"]');
                    if (query) query.value = '';
                    window.dispatchEvent(new CustomEvent('classroom-filters-reset'));
                    await this.fetchResults(url);
                },
                async fetchResults(url) {
                    this.loading = true;
                    try {
                        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                        if (!response.ok) throw new Error('Lỗi tải danh sách lớp học');
                        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const next = parsed.getElementById('classrooms-results-region');
                        const current = document.getElementById('classrooms-results-region');
                        if (!next || !current) throw new Error('Không tìm thấy vùng kết quả lớp học');
                        current.replaceWith(next);
                        window.history.pushState({}, '', url);
                        this.bindPagination();
                    } catch (error) {
                        console.error(error);
                        alert('Có lỗi xảy ra khi tải danh sách lớp học. Vui lòng thử lại.');
                    } finally { this.loading = false; }
                },
                bindPagination() {
                    document.querySelectorAll('#classrooms-pagination a').forEach((link) => {
                        link.addEventListener('click', (event) => {
                            event.preventDefault();
                            if (link.href) this.fetchResults(link.href);
                        });
                    });
                },
                init() { this.bindPagination(); },
            };
        }
    </script>
    </div>
</x-layouts.admin>
