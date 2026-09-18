@php
    use Modules\QuestionBank\Enums\SessionStatus;

    $statusLabels = [
        'active' => 'Đang làm',
        'paused' => 'Tạm dừng',
        'completed' => 'Đã xong',
        'expired' => 'Hết giờ',
        'abandoned' => 'Đã bỏ',
    ];
@endphp

<x-layouts.app title="Kỳ thi">
    <section class="mx-auto max-w-container-max p-4 sm:p-6 md:p-10">
        @if (session('status'))
            <div class="mb-6 rounded-xl border border-primary/20 bg-primary-container/30 px-4 py-3 text-sm font-semibold text-on-primary-container">
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
                    Chọn kỳ thi theo ma trận đề bộ — hệ thống tạo bài thi riêng với đủ số câu, phân bổ và thời gian tương ứng.
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
            <p class="mt-1 text-sm text-on-surface-variant">Mỗi lần tạo sẽ sinh một bài thi mới từ ma trận (không trùng đề với lần trước).</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @forelse ($blueprintCards as $card)
                @php
                    $locked = ! $canStartExam;
                    $ready = (bool) ($card['ready'] ?? false);
                @endphp
                <article class="flex h-full flex-col rounded-2xl border border-outline-variant bg-white p-5 shadow-sm">
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
                                        {{ $card['description'] ?: 'Ma trận đề thi theo cấu trúc chuẩn.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        @if ($locked)
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

                    @if (! $ready && $card['reason'])
                        <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">{{ $card['reason'] }}</p>
                    @endif

                    <div class="mt-5 mt-auto">
                        @if ($locked)
                            <a href="{{ route('billing.plans') }}"
                                class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-primary/30 px-4 text-sm font-bold text-primary hover:bg-primary/5">
                                <span class="material-symbols-outlined text-[18px]">lock</span>
                                Nâng cấp để tạo bài thi
                            </a>
                        @elseif (! $ready)
                            <button type="button" disabled
                                class="inline-flex h-11 w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-outline px-4 text-sm font-bold text-white">
                                Kỳ thi chưa sẵn sàng
                            </button>
                        @else
                            <form method="POST" action="{{ route('exam.from-blueprint', $card['id']) }}" class="w-full">
                                @csrf
                                <button type="submit"
                                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-bold text-white transition-colors hover:bg-primary/90">
                                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                                    Tạo bài thi
                                </button>
                            </form>
                        @endif
                    </div>
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

        @if ($recentExams->isNotEmpty())
            <section class="mt-10">
                <div class="mb-4">
                    <h2 class="text-xl font-bold text-on-surface">Bài thi của bạn</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">Các bài thi đã tạo từ ma trận — có thể làm lại cùng đề.</p>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($recentExams as $exam)
                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-outline-variant bg-white p-4 shadow-sm">
                            <div class="min-w-0">
                                <p class="font-bold text-on-surface line-clamp-1">{{ $exam->title }}</p>
                                <p class="mt-1 text-sm text-on-surface-variant">
                                    {{ $exam->questions_count }} câu · {{ $exam->duration_minutes }} phút
                                    · {{ $exam->created_at?->diffForHumans() }}
                                </p>
                            </div>
                            @if ($canStartExam)
                                <form method="POST" action="{{ route('exam.start', $exam) }}">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex h-10 items-center gap-1 rounded-xl border border-outline-variant px-3 text-sm font-bold text-on-surface hover:bg-surface-container-low">
                                        <span class="material-symbols-outlined text-[18px]">play_arrow</span>
                                        Làm
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="mt-10">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-on-surface">Phiên thi gần đây</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">Tiếp tục phiên đang làm hoặc xem lại kết quả đã nộp.</p>
                </div>
            </div>

            @if ($recentSessions->isEmpty())
                <div class="rounded-2xl border border-dashed border-outline-variant bg-white px-6 py-12 text-center">
                    <span class="material-symbols-outlined mb-3 text-5xl text-outline">assignment</span>
                    <p class="font-bold text-on-surface">Chưa có phiên thi nào</p>
                    <p class="mt-2 text-sm text-on-surface-variant">Tạo bài thi từ kỳ thi ở trên để bắt đầu.</p>
                </div>
            @else
                <div class="overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-sm">
                    @foreach ($recentSessions as $session)
                        @php
                            $examId = $session->exam_id
                                ?? (is_array($session->filters) ? ($session->filters['exam_id'] ?? null) : null);
                            $examTitle = $examId ? \Modules\Exam\Models\Exam::find($examId)?->title : 'Bài thi';
                            $status = $session->status->value;
                            $targetRoute = $session->status === SessionStatus::Completed
                                ? route('exam.summary', $session)
                                : route('exam.session', $session);
                        @endphp
                        <a href="{{ $targetRoute }}"
                            class="flex flex-col gap-3 border-b border-outline-variant/70 p-4 transition-colors last:border-0 hover:bg-surface-container-low sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-bold text-on-surface">{{ $examTitle }}</p>
                                <p class="mt-1 text-sm text-on-surface-variant">
                                    {{ $session->answered_count }}/{{ $session->total }} câu đã trả lời · {{ $session->updated_at?->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full bg-surface-container px-2.5 py-1 text-xs font-bold text-on-surface-variant">
                                    {{ $statusLabels[$status] ?? $status }}
                                </span>
                                <span class="material-symbols-outlined text-[20px] text-primary">arrow_forward</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </section>
</x-layouts.app>
