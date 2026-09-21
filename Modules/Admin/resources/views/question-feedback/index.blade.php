@php
    $statusTone = [
        'pending' => 'border-outline-variant text-on-surface',
        'reviewing' => 'border-outline-variant text-on-surface',
        'resolved' => 'border-outline-variant text-on-surface',
        'dismissed' => 'border-outline-variant text-on-surface-variant',
    ];
@endphp

<x-layouts.admin title="Phản hồi câu hỏi">
    <div x-data="adminQuestionFeedbackFilter()" class="space-y-6">
    <x-admin.page-header title="Quản lý phản hồi câu hỏi"
        description="Xem, lọc và xử lý phản hồi của học viên về câu hỏi, kiến thức và đáp án." />

    <x-admin.flash />

    <div class="mb-6 grid gap-3 sm:grid-cols-4">
        @foreach ($statuses as $value => $label)
            @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.question-feedback.index'))
<a href="{{ route('admin.question-feedback.index', ['status' => $value]) }}"
                class="rounded-xl border border-outline-variant bg-surface p-4 transition hover:bg-surface-container-low">
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ $label }}</p>
                <p class="mt-2 text-2xl font-bold text-on-surface">{{ number_format((int) ($statusCounts[$value] ?? 0)) }}</p>
            </a>
@endif
        @endforeach
    </div>

    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.question-feedback.index'))
<form method="get" action="{{ route('admin.question-feedback.index') }}" role="search"
        aria-labelledby="question-feedback-filter-heading" aria-describedby="question-feedback-filter-description"
        @submit.prevent="applyFilters()"
        class="mb-6 space-y-4 rounded-xl border border-outline-variant bg-surface p-4">
        <div>
            <h2 id="question-feedback-filter-heading" class="font-label-lg font-semibold text-on-surface">Tìm kiếm phản hồi câu hỏi</h2>
            <p id="question-feedback-filter-description" class="mt-1 font-body-sm text-on-surface-variant">Tìm theo nội dung, câu hỏi hoặc người gửi; sau đó có thể thu hẹp theo trạng thái, vị trí và loại phản hồi.</p>
        </div>
        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(280px,1.5fr)_repeat(3,minmax(150px,1fr))_auto]">
            <div class="sm:col-span-2 xl:col-auto">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="feedback-q">Tìm kiếm</label>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-on-surface-variant" aria-hidden="true">search</span>
                    <input id="feedback-q" name="q" value="{{ $filters['q'] }}" type="search" placeholder="Nội dung, câu hỏi hoặc người gửi" autocomplete="off"
                        class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low py-2 pl-10 pr-3 font-body-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
            </div>
            <div class="min-w-0">
                <x-admin.multi-select-filter name="status" label="Trạng thái" :options="collect($statuses)->map(fn ($label, $value) => ['id' => $value, 'label' => $label])->values()->all()" :selected="$filters['status']" />
            </div>
            <div class="min-w-0">
                <x-admin.multi-select-filter name="target" label="Vị trí" :options="collect($targets)->map(fn ($label, $value) => ['id' => $value, 'label' => $label])->values()->all()" :selected="$filters['target']" />
            </div>
            <div class="min-w-0">
                <x-admin.multi-select-filter name="category" label="Loại phản hồi" :options="collect($categories)->map(fn ($label, $value) => ['id' => $value, 'label' => $label])->values()->all()" :selected="$filters['category']" />
            </div>
            <div class="flex self-end gap-2 sm:col-span-2 xl:col-auto">
                <button type="submit" :disabled="loading" aria-label="Tìm kiếm phản hồi câu hỏi"
                    class="inline-flex h-11 w-36 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 font-label-md font-medium text-on-primary transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-primary/40 disabled:opacity-50">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true" x-text="loading ? 'progress_activity' : 'search'">search</span>
                    <span class="whitespace-nowrap" x-text="loading ? 'Đang tải' : 'Tìm kiếm'">Tìm kiếm</span>
                </button>
                <button type="button" @click="resetFilters(@js(route('admin.question-feedback.index')))" :disabled="loading" aria-label="Xoá bộ lọc phản hồi câu hỏi"
                    class="inline-flex h-11 w-28 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-3 font-label-md font-medium text-on-surface-variant transition hover:bg-surface-container-low focus-visible:ring-2 focus-visible:ring-primary/20 disabled:opacity-50">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span><span>Xoá</span>
                </button>
            </div>
        </div>
    </form>
@endif

    <div id="question-feedback-results-region">
    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-outline-variant text-sm">
                <caption class="sr-only">Danh sách phản hồi câu hỏi của học viên</caption>
                <thead class="bg-surface-container-low text-left text-xs uppercase tracking-wide text-on-surface-variant">
                    <tr>
                        <th scope="col" class="px-4 py-3">Phản hồi</th>
                        <th scope="col" class="px-4 py-3">Câu hỏi</th>
                        <th scope="col" class="px-4 py-3">Người gửi</th>
                        <th scope="col" class="px-4 py-3">Thời gian</th>
                        <th scope="col" class="px-4 py-3">Trạng thái</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($feedbackItems as $feedback)
                        <tr class="align-top">
                            <td class="max-w-md px-4 py-4">
                                <div class="mb-2 flex flex-wrap gap-1.5">
                                    <span class="rounded-full border border-outline-variant px-2 py-0.5 text-xs font-medium text-on-surface">
                                        {{ $targets[$feedback->target] ?? $feedback->target }}
                                    </span>
                                    <span class="rounded-full border border-outline-variant px-2 py-0.5 text-xs font-medium text-on-surface">
                                        {{ $categories[$feedback->category] ?? $feedback->category }}
                                    </span>
                                </div>
                                <div x-data="{ expanded: false, truncated: false }"
                                    x-init="$nextTick(() => truncated = $refs.preview.scrollHeight > $refs.preview.clientHeight + 1)">
                                    <p x-ref="preview" x-show="!expanded"
                                        class="line-clamp-2 whitespace-pre-line leading-5 text-on-surface">
                                        {{ $feedback->message ?: 'Không có ghi chú thêm.' }}
                                    </p>
                                    <p x-show="expanded" x-cloak
                                        class="whitespace-pre-line leading-5 text-on-surface">
                                        {{ $feedback->message ?: 'Không có ghi chú thêm.' }}
                                    </p>
                                    <button type="button" x-show="truncated || expanded" x-cloak
                                        @click="expanded = !expanded"
                                        :aria-expanded="expanded"
                                        class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                                        <span x-text="expanded ? 'Thu gọn' : 'Chi tiết'"></span>
                                        <span class="material-symbols-outlined text-[16px] transition-transform"
                                            :class="expanded ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
                                    </button>
                                </div>
                                @if ($feedback->option)
                                    <p class="mt-2 text-xs text-on-surface-variant">
                                        Đáp án {{ $feedback->option->label }}: {{ \Illuminate\Support\Str::limit(strip_tags($feedback->option->content), 100) }}
                                    </p>
                                @endif
                            </td>
                            <td class="max-w-sm px-4 py-4">
                                @if ($feedback->question)
                                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.questions.edit'))
<a href="{{ route('admin.questions.edit', $feedback->question) }}"
                                        class="font-semibold text-primary hover:underline">
                                        {{ \Illuminate\Support\Str::limit(strip_tags($feedback->question->stem), 120) }}
                                    </a>
@endif
                                    <p class="mt-1 text-xs text-on-surface-variant">ID: {{ $feedback->question_id }}</p>
                                @else
                                    <span class="text-on-surface-variant">Câu hỏi đã bị xóa</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-semibold text-on-surface">{{ $feedback->user?->name ?? 'Không rõ' }}</p>
                                <p class="text-xs text-on-surface-variant">{{ $feedback->user?->email }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-on-surface-variant">
                                {{ $feedback->created_at?->format('d/m/Y H:i') }}
                            </td>
                            <td class="min-w-56 px-4 py-4">
                                @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.question-feedback.update-status'))
<form method="post" action="{{ route('admin.question-feedback.update-status', $feedback) }}" class="space-y-2">
                                    @csrf
                                    @method('patch')
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusTone[$feedback->status] ?? $statusTone['pending'] }}">
                                        {{ $statuses[$feedback->status] ?? $feedback->status }}
                                    </span>
                                    <div class="flex gap-2">
                                        <select name="status" class="h-10 min-w-0 flex-1 rounded-lg border border-outline-variant bg-surface px-2 text-xs text-on-surface">
                                            @foreach ($statuses as $value => $label)
                                                <option value="{{ $value }}" @selected($feedback->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-primary px-3 text-xs font-semibold text-on-primary hover:opacity-90">
                                            Lưu
                                        </button>
                                    </div>
                                </form>
@endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-on-surface-variant">
                                Chưa có phản hồi phù hợp.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5" id="question-feedback-pagination">{{ $feedbackItems->links() }}</div>
    </div>

    <script>
        function adminQuestionFeedbackFilter() {
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
                    window.dispatchEvent(new CustomEvent('question-feedback-filters-reset'));
                    await this.fetchResults(url);
                },
                async fetchResults(url) {
                    this.loading = true;
                    try {
                        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                        if (!response.ok) throw new Error('Lỗi tải phản hồi câu hỏi');
                        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const next = parsed.getElementById('question-feedback-results-region');
                        const current = document.getElementById('question-feedback-results-region');
                        if (!next || !current) throw new Error('Không tìm thấy vùng kết quả phản hồi câu hỏi');
                        current.replaceWith(next);
                        window.history.pushState({}, '', url);
                        this.bindPagination();
                    } catch (error) {
                        console.error(error);
                        alert('Có lỗi xảy ra khi tải phản hồi câu hỏi. Vui lòng thử lại.');
                    } finally { this.loading = false; }
                },
                bindPagination() {
                    document.querySelectorAll('#question-feedback-pagination a').forEach((link) => {
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
