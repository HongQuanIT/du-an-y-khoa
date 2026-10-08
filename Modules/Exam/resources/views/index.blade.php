@php
    use Modules\QuestionBank\Enums\SessionStatus;

    $statusLabels = [
        'active' => 'Đang làm',
        'paused' => 'Tạm dừng',
        'completed' => 'Đã xong',
    ];
@endphp

<x-layouts.app title="Kỳ thi">
    <section class="mx-auto max-w-container-max p-4 sm:p-6 md:p-10">
        @if (session('status'))
            <div class="mb-6 rounded-xl border border-primary/20 bg-primary-container/30 px-4 py-3 text-sm font-semibold text-white">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-error/30 bg-error-container/30 px-4 py-3 text-sm font-semibold text-on-error-container">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="mb-2 text-sm font-bold uppercase tracking-wide text-primary">Mô phỏng kỳ thi</p>
                <h1 class="font-headline-lg text-headline-lg font-bold text-on-surface">Kỳ thi</h1>
                <p class="mt-3 text-sm leading-6 text-on-surface-variant sm:text-base">
                    Làm bài thi mẫu cố định miễn phí. Học viên Premium có thể tạo đề ngẫu nhiên theo ma trận kỳ thi và vào làm ngay.
                </p>
            </div>
            @unless ($canStartExam)
                <a href="{{ route('billing.plans') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-primary/90">
                    <span class="material-symbols-outlined text-[18px]">workspace_premium</span>
                    Mở khóa mô phỏng thi
                </a>
            @endunless
        </div>

        <div class="mb-4">
            <h2 class="text-lg font-bold text-on-surface">Chọn kỳ thi</h2>
            <p class="mt-1 text-sm text-on-surface-variant">Kỳ thi theo chức danh của bạn · 40% câu dễ, 30% trung bình, 30% khó (làm tròn theo số câu).</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @forelse ($blueprintCards as $card)
                @php
                    $locked = ! $canStartExam;
                    $ready = (bool) ($card['ready'] ?? false);
                @endphp
                <article x-data="{ confirming: false }" class="flex h-full flex-col rounded-2xl border border-outline-variant bg-white p-5 shadow-sm">
                    <div class="flex min-h-[92px] items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start gap-3">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-white">
                                    <span class="material-symbols-outlined text-[24px]">account_tree</span>
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-lg font-bold text-on-surface line-clamp-2">{{ $card['name'] }}</h2>
                                    @if ($card['code'])
                                        <p class="mt-0.5 font-mono text-[11px] text-on-surface-variant">{{ $card['code'] }}</p>
                                    @endif
                                    <p class="mt-1 text-sm leading-6 text-on-surface-variant line-clamp-2">
                                        {{ $card['description'] ?: 'Kỳ thi có ma trận đề.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        @if ($locked && empty($card['sample_exam_id']))
                            <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Premium</span>
                        @elseif (! $ready)
                            <span class="shrink-0 rounded-full bg-surface-container-high px-2.5 py-1 text-xs font-bold text-on-surface-variant">Chưa sẵn sàng</span>
                        @endif
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                        <div class="flex min-h-[84px] flex-col justify-between rounded-xl bg-surface-container-low p-3">
                            <p class="text-xs font-bold text-on-surface-variant">Số câu</p>
                            <p class="mt-1 text-2xl font-bold text-on-surface">{{ $ready ? $card['question_count'] : '—' }}</p>
                        </div>
                        <div class="flex min-h-[84px] flex-col justify-between rounded-xl bg-surface-container-low p-3">
                            <p class="text-xs font-bold text-on-surface-variant">Thời gian</p>
                            <p class="mt-1 text-2xl font-bold text-on-surface">{{ $ready ? $card['duration_minutes']."'" : '—' }}</p>
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-on-surface-variant">
                        {{ $card['sections_count'] }} phần · {{ $card['topic_count'] }} chủ đề
                    </p>
                    @if ($ready)
                        <p class="mt-2 text-xs text-on-surface-variant">Đề mới sau làm tròn: {{ $card['difficulty_quotas']['easy'] }} dễ · {{ $card['difficulty_quotas']['medium'] }} trung bình · {{ $card['difficulty_quotas']['hard'] }} khó.</p>
                    @endif

                    @if (! $ready && $card['reason'])
                        <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">{{ $card['reason'] }}</p>
                    @elseif (! $card['premium_ready'] && $card['premium_reason'])
                        <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">{{ $card['premium_reason'] }}</p>
                    @endif

                    <div class="mt-5 mt-auto">
                        @if ($card['sample_exam_id'])
                            <p class="mb-2 text-sm text-on-surface-variant">Bài mẫu: {{ $card['sample_question_count'] }} câu · {{ $card['sample_duration_minutes'] }} phút · Miễn phí</p>
                            <p class="mb-2 text-xs text-on-surface-variant">{{ $card['sample_difficulty_quotas']['easy'] ?? 0 }} dễ · {{ $card['sample_difficulty_quotas']['medium'] ?? 0 }} trung bình · {{ $card['sample_difficulty_quotas']['hard'] ?? 0 }} khó.</p>
                            <form method="POST" action="{{ route('exam.start', $card['sample_exam_id']) }}" class="mb-3">
                                @csrf
                                <button class="h-11 w-full rounded-xl bg-primary px-4 text-sm font-bold text-white">Làm bài thi mẫu</button>
                            </form>
                        @else
                            <p class="mb-3 text-sm text-on-surface-variant">Chưa có bài thi mẫu được xuất bản.</p>
                        @endif
                        @if ($locked)
                            <a href="{{ route('billing.plans') }}"
                                class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-primary/30 px-4 text-sm font-bold text-primary hover:bg-primary/5">
                                <span class="material-symbols-outlined text-[18px]">lock</span>
                                Nâng cấp để tạo đề mới
                            </a>
                        @elseif (! $card['premium_ready'])
                            <button type="button" disabled
                                class="inline-flex h-11 w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-outline px-4 text-sm font-bold text-white">
                                Kỳ thi chưa sẵn sàng
                            </button>
                        @else
                            <div class="w-full">
                                <button type="button" @click="confirming = true"
                                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-bold text-white transition-colors hover:bg-primary/90">
                                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                                    Tạo đề mới · Premium
                                </button>
                            </div>
                        @endif
                    </div>
                    @if (! $locked && $card['premium_ready'])
                        <div x-show="confirming" x-cloak @keydown.escape.window="confirming = false"
                            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true"
                            aria-labelledby="create-exam-title-{{ $card['id'] }}" @click.self="confirming = false">
                            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                                <h3 id="create-exam-title-{{ $card['id'] }}" class="text-lg font-bold text-on-surface">Tạo đề thi mới</h3>
                                <p class="mt-3 text-sm leading-6 text-on-surface-variant">
                                    Kỳ thi này có bài mẫu cố định. Đề Premium mới sẽ chọn ngẫu nhiên số câu ở từng phần trong khoảng tỉ trọng min/max của ma trận, rồi chia cho các chủ đề theo tỉ trọng đã cấu hình.
                                </p>
                                <p class="mt-2 text-sm font-semibold text-on-surface">Tổng đề: {{ $card['question_count'] }} câu. Sau khi tạo, bạn sẽ vào phiên làm bài ngay.</p>
                                <ul class="mt-3 max-h-40 space-y-1 overflow-y-auto text-sm text-on-surface-variant">
                                    @foreach ($card['section_ranges'] as $range)
                                        <li class="flex justify-between gap-3"><span>{{ $range['name'] }}</span><strong class="shrink-0 text-on-surface">{{ $range['min'] }}–{{ $range['max'] }} câu</strong></li>
                                    @endforeach
                                </ul>
                                <div class="mt-6 flex justify-end gap-3">
                                    <button type="button" @click="confirming = false" class="rounded-xl border border-outline-variant px-4 py-2 text-sm font-bold text-on-surface">Hủy</button>
                                    <form method="POST" action="{{ route('exam.from-blueprint', $card['id']) }}">
                                        @csrf
                                        <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white">Tạo và làm ngay</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-outline-variant bg-white px-6 py-12 text-center">
                    <p class="font-bold text-on-surface">Chưa có kỳ thi nào.</p>
                    <p class="mt-2 text-sm text-on-surface-variant">Admin cần cấu hình ma trận đề thi trước.</p>
                </div>
            @endforelse
        </div>

        @if ($blueprintCards->hasPages())
            <div class="mt-8">
                {{ $blueprintCards->links() }}
            </div>
        @endif

        <section class="mt-10" aria-labelledby="recent-exams-heading">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 id="recent-exams-heading" class="text-xl font-bold text-on-surface">Phiên thi gần đây</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">Theo dõi tiến độ, tiếp tục làm bài hoặc xem lại câu hỏi đã nộp.</p>
                </div>
            </div>

            @if ($recentSessions->isEmpty())
                <div class="rounded-2xl border border-dashed border-outline-variant bg-white px-6 py-12 text-center">
                    <span class="material-symbols-outlined mb-3 text-5xl text-outline">assignment</span>
                    <p class="font-bold text-on-surface">Chưa có phiên thi nào</p>
                    <p class="mt-2 text-sm text-on-surface-variant">Chọn bài mẫu hoặc tạo đề Premium ở trên để bắt đầu làm bài.</p>
                </div>
            @else
                <ol class="grid gap-4 lg:grid-cols-2">
                    @foreach ($recentSessions as $session)
                        @php
                            $status = $session->status->value;
                            $completed = $session->status === SessionStatus::Completed;
                            $elapsedSeconds = max(0, (int) ($session->time_spent_seconds ?? 0));
                            $limitSeconds = max(0, (int) ($session->time_limit_seconds ?? (($session->exam?->duration_minutes ?? 0) * 60)));
                            $elapsedLabel = sprintf('%d:%02d', intdiv($elapsedSeconds, 60), $elapsedSeconds % 60);
                            $limitLabel = $limitSeconds > 0
                                ? sprintf('%d:%02d', intdiv($limitSeconds, 60), $limitSeconds % 60)
                                : '—';
                            $progress = $session->total > 0
                                ? min(100, (int) round($session->answered_count / $session->total * 100))
                                : 0;
                        @endphp
                        <li class="h-full">
                            <article class="flex h-full flex-col rounded-2xl border border-outline-variant bg-white p-5 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-primary">
                                            @if ($session->exam?->kind === 'personal')
                                                Đề Premium · Q{{ $session->premium_exam_number }}
                                            @elseif ($session->exam?->kind === 'sample')
                                                Bài thi mẫu
                                            @else
                                                Phiên thi
                                            @endif
                                        </p>
                                        <h3 class="text-base font-bold leading-6 text-on-surface">{{ $session->exam?->title ?? 'Bài thi' }}</h3>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold"
                                        @class([
                                            'bg-emerald-50 text-emerald-800' => $completed,
                                            'bg-indigo-50 text-indigo-800' => $status === 'paused',
                                            'bg-amber-50 text-amber-800' => $status === 'active',
                                        ])>
                                        {{ $statusLabels[$status] ?? $status }}
                                    </span>
                                </div>

                                <div class="mt-5 grid grid-cols-2 gap-3 rounded-xl bg-surface-container-low p-3 text-sm">
                                    <div>
                                        <p class="text-xs text-on-surface-variant">Câu đã trả lời</p>
                                        <p class="mt-1 font-bold tabular-nums text-on-surface">{{ $session->answered_count }}/{{ $session->total }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-on-surface-variant">Đã làm / giới hạn</p>
                                        <p class="mt-1 font-bold tabular-nums text-on-surface">{{ $elapsedLabel }} / {{ $limitLabel }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-surface-container-high"
                                    role="progressbar" aria-label="Tiến độ trả lời"
                                    aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}">
                                    <div class="h-full rounded-full bg-primary" style="width: {{ $progress }}%"></div>
                                </div>

                                <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-5">
                                    <p class="text-xs text-on-surface-variant">
                                        Cập nhật <time datetime="{{ $session->updated_at?->toIso8601String() }}">{{ $session->updated_at?->diffForHumans() }}</time>
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($completed)
                                            <a href="{{ route('exam.summary', $session) }}"
                                                class="inline-flex h-9 items-center gap-1 rounded-lg border border-outline-variant px-3 text-xs font-bold text-on-surface transition hover:bg-surface-container-low">
                                                Xem kết quả
                                            </a>
                                            @can('exam.review')
                                                <a href="{{ route('exam.review', $session) }}"
                                                    class="inline-flex h-9 items-center gap-1 rounded-lg bg-primary px-3 text-xs font-bold text-white transition hover:bg-primary/90">
                                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">rate_review</span>
                                                    Xem lại câu hỏi
                                                </a>
                                            @endcan
                                        @else
                                            <a href="{{ route('exam.session', $session) }}"
                                                class="inline-flex h-9 items-center gap-1 rounded-lg bg-primary px-3 text-xs font-bold text-white transition hover:bg-primary/90">
                                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">play_arrow</span>
                                                Tiếp tục làm
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        </li>
                    @endforeach
                </ol>
                @if ($recentSessions->hasPages())
                    <nav class="mt-6" aria-label="Trang phiên thi gần đây">
                        {{ $recentSessions->links() }}
                    </nav>
                @endif
            @endif
        </section>
    </section>
</x-layouts.app>
