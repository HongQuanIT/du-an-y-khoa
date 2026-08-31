@php
    $statusTone = [
        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
        'reviewing' => 'bg-sky-50 text-sky-700 border-sky-200',
        'resolved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'dismissed' => 'bg-surface-container-high text-on-surface-variant border-outline-variant',
    ];
@endphp

<x-layouts.admin title="Feedback câu hỏi">
    <x-admin.page-header title="Quản lý feedback câu hỏi"
        description="Xem, lọc và xử lý phản hồi của học viên về câu hỏi, kiến thức và đáp án." />

    <x-admin.flash />

    <div class="mb-6 grid gap-3 sm:grid-cols-4">
        @foreach ($statuses as $value => $label)
            <a href="{{ route('admin.question-feedback.index', ['status' => $value]) }}"
                class="rounded-xl border border-outline-variant bg-surface p-4 transition hover:border-primary hover:bg-primary/5">
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ $label }}</p>
                <p class="mt-2 text-2xl font-bold text-on-surface">{{ number_format((int) ($statusCounts[$value] ?? 0)) }}</p>
            </a>
        @endforeach
    </div>

    <form method="get" action="{{ route('admin.question-feedback.index') }}" role="search"
        aria-label="Lọc feedback câu hỏi"
        class="mb-6 grid grid-cols-1 items-end gap-4 rounded-xl border border-outline-variant bg-surface p-4 md:grid-cols-12">
        <label class="md:col-span-4">
            <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Tìm kiếm</span>
            <input name="q" value="{{ $filters['q'] }}" type="search"
                placeholder="Nội dung feedback, câu hỏi, người gửi"
                class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface">
        </label>
        <label class="md:col-span-2">
            <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Trạng thái</span>
            <select name="status" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface">
                <option value="">Tất cả</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="md:col-span-2">
            <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Vị trí</span>
            <select name="target" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface">
                <option value="">Tất cả</option>
                @foreach ($targets as $value => $label)
                    <option value="{{ $value }}" @selected($filters['target'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="md:col-span-2">
            <span class="mb-1.5 block text-sm font-medium text-on-surface-variant">Loại feedback</span>
            <select name="category" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-low px-3 text-sm text-on-surface">
                <option value="">Tất cả</option>
                @foreach ($categories as $value => $label)
                    <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="grid grid-cols-2 gap-2 md:col-span-2">
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-semibold text-on-primary hover:opacity-90">
                Lọc
            </button>
            <a href="{{ route('admin.question-feedback.index') }}"
                class="inline-flex h-11 items-center justify-center rounded-lg border border-outline-variant px-4 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">
                Xóa lọc
            </a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-outline-variant text-sm">
                <thead class="bg-surface-container-low text-left text-xs uppercase tracking-wide text-on-surface-variant">
                    <tr>
                        <th class="px-4 py-3">Feedback</th>
                        <th class="px-4 py-3">Câu hỏi</th>
                        <th class="px-4 py-3">Người gửi</th>
                        <th class="px-4 py-3">Thời gian</th>
                        <th class="px-4 py-3">Trạng thái</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($feedbackItems as $feedback)
                        <tr class="align-top">
                            <td class="max-w-md px-4 py-4">
                                <div class="mb-2 flex flex-wrap gap-1.5">
                                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                                        {{ $targets[$feedback->target] ?? $feedback->target }}
                                    </span>
                                    <span class="rounded-full bg-tertiary/10 px-2 py-0.5 text-xs font-semibold text-tertiary">
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
                                    <a href="{{ route('admin.questions.edit', $feedback->question) }}"
                                        class="font-semibold text-primary hover:underline">
                                        {{ \Illuminate\Support\Str::limit(strip_tags($feedback->question->stem), 120) }}
                                    </a>
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
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-on-surface-variant">
                                Chưa có feedback phù hợp.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $feedbackItems->links() }}</div>
</x-layouts.admin>
