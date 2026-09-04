@php
    $stemPreview = \Illuminate\Support\Str::limit(strip_tags($question->stem), 180);
    $ratePct = $stats['correct_rate'] !== null ? $stats['correct_rate'] * 100 : null;
    $updatedLabel = $question->stats_updated_at?->diffForHumans() ?? 'Chưa có rollup';
@endphp

<x-layouts.admin title="Thống kê câu hỏi">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <a href="{{ route('admin.questions.index') }}"
               class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl border border-outline-variant text-on-surface-variant transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            </a>
            <div>
                <h1 class="font-headline-sm font-bold text-on-surface">Thống kê câu hỏi</h1>
                <p class="mt-1 max-w-3xl font-body-sm text-on-surface-variant">
                    @if (filled($question->code))
                        <span class="font-mono font-semibold text-on-surface">{{ $question->code }}</span>
                        <span class="mx-1">·</span>
                    @endif
                    {{ $stemPreview }}
                </p>
                <p class="mt-1 font-label-sm text-on-surface-variant">
                    Cập nhật thống kê: {{ $updatedLabel }}
                    <span class="mx-1">·</span>
                    {{ $question->status->label() }}
                    <span class="mx-1">·</span>
                    {{ $question->is_free ? 'Miễn phí' : 'Premium' }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.questions.edit', $question) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[18px]">edit</span>
                Chỉnh sửa
            </a>
        </div>
    </div>

    <x-admin.flash />

    @if ($stats['quality_hint'])
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
            <div class="flex items-start gap-2">
                <span class="material-symbols-outlined text-[20px]">lightbulb</span>
                <p>{{ $stats['quality_hint'] }}</p>
            </div>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm">
            <p class="text-label-sm font-medium text-on-surface-variant">Tổng lượt làm</p>
            <p class="mt-1 text-headline-sm font-bold tabular-nums text-on-surface">{{ number_format($stats['total_attempts']) }}</p>
        </div>
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm">
            <p class="text-label-sm font-medium text-on-surface-variant">Tỉ lệ đúng</p>
            <p class="mt-1 text-headline-sm font-bold tabular-nums text-on-surface">
                {{ $ratePct === null ? '—' : number_format($ratePct, 1).'%' }}
            </p>
        </div>
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm">
            <p class="text-label-sm font-medium text-on-surface-variant">Trả lời đúng / sai</p>
            <p class="mt-1 text-headline-sm font-bold tabular-nums text-on-surface">
                {{ number_format($stats['correct_attempts']) }}
                <span class="text-base font-medium text-on-surface-variant">/</span>
                {{ number_format($stats['incorrect_attempts']) }}
            </p>
        </div>
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm">
            <p class="text-label-sm font-medium text-on-surface-variant">Tổng phản hồi</p>
            <p class="mt-1 text-headline-sm font-bold tabular-nums {{ $stats['total_reports'] > 0 ? 'text-red-700 dark:text-red-300' : 'text-on-surface' }}">
                {{ number_format($stats['total_reports']) }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
            <h2 class="mb-4 font-label-md font-semibold text-on-surface">Theo chế độ làm bài</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-4 border-b border-outline-variant/60 pb-3">
                    <dt class="text-on-surface-variant">Study mode</dt>
                    <dd class="font-semibold tabular-nums text-on-surface">{{ number_format($stats['study_mode_attempts']) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 border-b border-outline-variant/60 pb-3">
                    <dt class="text-on-surface-variant">Chế độ thi</dt>
                    <dd class="font-semibold tabular-nums text-on-surface">{{ number_format($stats['exam_mode_attempts']) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-on-surface-variant">Điểm trung bình</dt>
                    <dd class="font-semibold tabular-nums text-on-surface">
                        {{ $stats['average_score'] === null ? '—' : number_format($stats['average_score'], 2) }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
            <h2 class="mb-4 font-label-md font-semibold text-on-surface">Phản hồi theo lý do</h2>
            @if ($stats['reports_by_reason'] === [])
                <p class="text-sm text-on-surface-variant">Chưa có thống kê chi tiết phản hồi trong bộ nhớ đệm.</p>
            @else
                <dl class="space-y-3 text-sm">
                    @foreach ($stats['reports_by_reason'] as $reason => $count)
                        <div class="flex items-center justify-between gap-4 border-b border-outline-variant/60 pb-3 last:border-0 last:pb-0">
                            <dt class="text-on-surface-variant">{{ $reason }}</dt>
                            <dd class="font-semibold tabular-nums text-on-surface">{{ number_format($count) }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        <section class="rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm lg:col-span-2">
            <h2 class="mb-4 font-label-md font-semibold text-on-surface">Thông tin câu hỏi</h2>
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-on-surface-variant">Độ khó</dt>
                    <dd class="mt-0.5 font-semibold text-on-surface">{{ $question->difficulty->label() }}</dd>
                </div>
                @if ($isReviewer)
                    <div>
                        <dt class="text-on-surface-variant">Người tạo</dt>
                        <dd class="mt-0.5 font-semibold text-on-surface">{{ $question->creator?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-on-surface-variant">Người duyệt</dt>
                        <dd class="mt-0.5 font-semibold text-on-surface">{{ $question->reviewer?->name ?? '—' }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-on-surface-variant">Phiên bản</dt>
                    <dd class="mt-0.5 font-semibold text-on-surface">{{ $question->version > 0 ? $question->version : 'Chưa có' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-on-surface-variant">Danh mục y khoa</dt>
                    <dd class="mt-1 flex flex-wrap gap-1">
                        @forelse ($question->medicalTaxonomyNodes as $node)
                            <span class="rounded-lg bg-surface-container-high px-2.5 py-1 text-xs font-semibold text-on-surface">{{ $node->name }}</span>
                        @empty
                            <span class="text-on-surface-variant">—</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
            <p class="mt-4 text-xs text-on-surface-variant">
                Số liệu lấy từ <code class="rounded bg-surface-container-high px-1 py-0.5">stats_cache</code> (rollup job), không aggregate realtime từ attempts.
            </p>
        </section>
    </div>
</x-layouts.admin>
