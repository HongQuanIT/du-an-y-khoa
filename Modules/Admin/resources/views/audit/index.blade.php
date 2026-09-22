<x-layouts.admin title="Nhật ký hoạt động">
    <div x-data="adminAuditFilter()" class="space-y-6">
    <x-admin.page-header title="Nhật ký hoạt động"
        description="Nhật ký bất biến các thao tác nhạy cảm (chỉ đọc)." />

    <x-admin.flash />

    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.audit.index'))
<form id="audit-filter-form" method="get" action="{{ route('admin.audit.index') }}" role="search"
        aria-label="Tìm kiếm nhật ký hoạt động"
        @submit.prevent="applyFilters()"
        class="grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 md:grid-cols-12">
        <div class="relative min-w-0 md:col-span-3"
            x-data='{
                open: false,
                query: @json((string) ($filters["action"] ?? "")),
                items: @json($actionSuggestions),
                matches() {
                    const keyword = this.query.trim().toLocaleLowerCase();
                    const rows = keyword === ""
                        ? this.items
                        : this.items.filter(item =>
                            item.label.toLocaleLowerCase().includes(keyword)
                            || item.value.toLocaleLowerCase().includes(keyword)
                        );

                    return rows.slice(0, 8);
                }
            }'
            @click.outside="open = false"
            @audit-filters-reset.window="query = ''">
            <label class="mb-1.5 block text-sm font-medium text-on-surface-variant" for="action">Hành động</label>
            <div class="relative">
                <input id="action" name="action" x-model="query" type="search"
                    @focus="open = true" @input="open = true" @keydown.escape="open = false"
                    placeholder="Nhập mã hoặc tên hành động" autocomplete="new-password" autocapitalize="none"
                    spellcheck="false" data-form-type="other" data-lpignore="true" data-1p-ignore="true"
                    class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 pl-9 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                <span class="material-symbols-outlined pointer-events-none absolute top-2.5 left-2.5 text-[20px] text-on-surface-variant/70" aria-hidden="true">search</span>
            </div>

            <div x-show="open && matches().length > 0" x-cloak
                class="absolute inset-x-0 z-40 mt-1.5 max-h-72 overflow-y-auto rounded-xl border border-outline-variant bg-surface p-2 shadow-xl">
                <template x-for="item in matches()" :key="item.value">
                    <button type="button"
                        @click="query = item.value; open = false"
                        class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-left text-sm hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[16px] text-primary" aria-hidden="true">bolt</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-on-surface" x-text="item.label"></span>
                            <span class="block truncate font-mono text-[11px] text-on-surface-variant" x-text="item.value"></span>
                        </span>
                    </button>
                </template>
            </div>
        </div>

        <div class="min-w-0 md:col-span-2">
            <label class="mb-1.5 block text-sm font-medium text-on-surface-variant" for="actor">Người thực hiện</label>
            <input id="actor" name="actor" value="{{ $filters['actor'] }}" type="search"
                placeholder="Nhập tên hoặc ID" autocomplete="off"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
        </div>

        <div class="min-w-0 md:col-span-2">
            <x-admin.multi-select-filter
                name="actor_role"
                label="Vai trò"
                placeholder="Tất cả"
                :options="collect($roles)->map(fn ($role) => ['id' => $role->value, 'label' => $role->label()])->values()->all()"
                :selected="$filters['actor_role']"
            />
        </div>

        <div class="min-w-0 md:col-span-2">
            <label class="mb-1.5 block text-sm font-medium text-on-surface-variant" for="ip">Địa chỉ IP</label>
            <input id="ip" name="ip" value="{{ $filters['ip'] }}" type="search"
                placeholder="Ví dụ: 192.168.1.1" autocomplete="off" spellcheck="false"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
        </div>

        <div class="md:col-span-3">
            <span class="mb-1.5 block text-sm font-medium text-transparent select-none" aria-hidden="true">&nbsp;</span>
            <x-admin.filter-action-buttons
                fill
                :reset-url="route('admin.audit.index')"
                search-aria-label="Tìm kiếm nhật ký hoạt động"
                reset-aria-label="Xoá bộ lọc nhật ký hoạt động"
            />
        </div>
    </form>
@endif

    <div id="audit-results-region" aria-live="polite">
    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <caption class="sr-only">Danh sách nhật ký hoạt động quản trị</caption>
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-label-md text-on-surface-variant">
                <tr>
                    <th scope="col" class="px-4 py-3">Thời gian</th>
                    <th scope="col" class="px-4 py-3">Người thực hiện</th>
                    <th scope="col" class="px-4 py-3">Hành động</th>
                    <th scope="col" class="px-4 py-3">Đối tượng</th>
                    <th scope="col" class="px-4 py-3">IP</th>
                    <th scope="col" class="px-4 py-3">Thiết bị truy cập</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3 whitespace-nowrap text-on-surface-variant">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-3">
                            @if ($log->actor)
                                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.users.show'))
<a href="{{ route('admin.users.show', $log->actor) }}" class="text-primary hover:underline">{{ $log->actor->name }}</a>
@endif
                                <div class="font-label-sm text-label-sm text-on-surface-variant">#{{ $log->actor_id }}</div>
                                <div class="whitespace-nowrap font-label-sm text-label-sm text-on-surface-variant">{{ \App\Support\Enums\Role::tryFromName($log->actor_role)?->label() ?? '—' }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="whitespace-nowrap font-label-md text-on-surface">{{ $log->actionLabel() }}</div>
                            <div class="whitespace-nowrap font-mono text-xs text-on-surface-variant">{{ $log->action }}</div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">
                            @if ($log->auditable_type)
                                @if ($log->auditable_type === $userMorphClass)
                                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.users.show'))
<a href="{{ route('admin.users.show', $log->auditable_id) }}" class="whitespace-nowrap text-primary hover:underline">
                                        Người dùng #{{ $log->auditable_id }}
                                    </a>
@endif
                                @elseif ($log->auditable_type === $questionMorphClass)
                                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.edit'))
<a href="{{ route('admin.questions.edit', $log->auditable_id) }}" class="whitespace-nowrap text-primary hover:underline">
                                        Câu hỏi #{{ $log->auditable_id }}
                                    </a>
@endif
                                @else
                                    <span class="whitespace-nowrap">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $log->ip ?? '—' }}</td>
                        <td class="min-w-52 px-4 py-3">
                            <div class="font-label-md text-on-surface">{{ $log->device_name ?? $log->deviceTypeLabel() }}</div>
                            <div class="text-xs text-on-surface-variant">{{ $log->operating_system ?? 'Không rõ hệ điều hành' }}</div>
                            <div class="text-xs text-on-surface-variant">{{ $log->browser ?? 'Không rõ trình duyệt' }}</div>
                        </td>
                        <td class="px-4 py-3 text-end">
                            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.audit.show'))
<a href="{{ route('admin.audit.show', $log) }}" class="font-label-md text-primary hover:underline">Chi tiết</a>
@endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-on-surface-variant">Chưa có bản ghi nhật ký.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>

    @if ($logs->hasPages())
        <div class="mt-4" id="audit-pagination">{{ $logs->links() }}</div>
    @endif
    </div>

    <script>
        function adminAuditFilter() {
            return {
                loading: false,
                filterForm() { return this.$root.querySelector('#audit-filter-form'); },
                async applyFilters() {
                    const form = this.filterForm();
                    if (!form) return;
                    const url = new URL(form.getAttribute('action'), window.location.origin);
                    const params = new URLSearchParams(new FormData(form));
                    params.delete('cursor');
                    url.search = params.toString();
                    await this.fetchResults(url.toString());
                },
                async resetFilters(url) {
                    const form = this.filterForm();
                    form?.reset();
                    window.dispatchEvent(new CustomEvent('audit-filters-reset'));
                    await this.fetchResults(url);
                },
                async fetchResults(url) {
                    this.loading = true;
                    try {
                        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                        if (!response.ok) throw new Error('Lỗi tải nhật ký hoạt động');
                        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const next = parsed.getElementById('audit-results-region');
                        const current = document.getElementById('audit-results-region');
                        if (!next || !current) throw new Error('Không tìm thấy vùng kết quả nhật ký');
                        current.replaceWith(next);
                        window.history.pushState({}, '', url);
                        this.bindPagination();
                    } catch (error) {
                        console.error(error);
                        alert('Có lỗi xảy ra khi tải nhật ký hoạt động. Vui lòng thử lại.');
                    } finally { this.loading = false; }
                },
                bindPagination() {
                    document.querySelectorAll('#audit-pagination a').forEach((link) => {
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
