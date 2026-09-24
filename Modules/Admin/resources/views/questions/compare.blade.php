<x-layouts.admin title="So sánh câu hỏi — {{ $question->code }}">
    <header class="mb-6 flex flex-col gap-4 border-b border-outline-variant pb-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            <a href="{{ route(\App\Support\Auth\PortalRoute::content('questions.edit'), $question) }}"
                aria-label="Quay lại câu hỏi"
                class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">arrow_back</span>
            </a>
            <div class="min-w-0">
                <h1 class="font-headline-md text-headline-md font-bold text-on-surface">So sánh với bản đang dùng</h1>
                <p class="mt-1 font-mono text-sm font-semibold tracking-wide text-on-surface-variant">{{ $question->code }}</p>
                <p class="mt-1.5 text-sm text-on-surface-variant">
                    @if ($comparison['can_compare'])
                        Bên trái là bản đang dùng (v{{ $comparison['published_version'] }}).
                        Bên phải là {{ $proposedTitle }} — chưa thay thế bản live cho đến khi xuất bản lại.
                    @else
                        Câu này chưa có bản live. Cột phải là nội dung đang lưu.
                    @endif
                </p>
            </div>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2 lg:justify-end">
            <a href="{{ route(\App\Support\Auth\PortalRoute::content('questions.edit'), $question) }}"
                class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">edit</span>
                {{ $canEditContent ? 'Quay lại chỉnh sửa' : 'Quay lại chi tiết' }}
            </a>
            @if ((int) $question->version > 0)
                <a href="{{ route('admin.questions.versions.index', $question) }}"
                    class="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-low">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">history</span>
                    Lịch sử phiên bản
                </a>
            @endif
        </div>
    </header>

    <x-admin.flash />

    @include('questionbank::partials.question-review-comparison', [
        'comparison' => $comparison,
        'heading' => 'Đối chiếu nội dung',
        'proposedTitle' => $proposedTitle,
        'proposedBadge' => $proposedBadge,
        'newCopy' => 'Câu này chưa từng xuất bản — bên trái trống, bên phải là nội dung đang lưu.',
        'sameCopy' => 'Bản làm việc trùng với bản đang dùng.',
    ])
</x-layouts.admin>
