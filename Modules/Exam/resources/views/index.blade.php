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
                    Làm bài thi thử với cấu trúc mô phỏng kỳ thi thật. Thời gian sẽ được tính lùi giống hệt thi thật.
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

        <div class="grid gap-4 lg:grid-cols-3">
            @forelse ($examCards as $exam)
                @php
                    $locked = ! $canStartExam;
                    $session = $exam['session'] ?? null;
                    $isCompleted = $session && $session->status === SessionStatus::Completed;
                    $isActive = $session && $session->status !== SessionStatus::Completed;
                @endphp
                <article class="flex h-full flex-col rounded-2xl border {{ $isCompleted ? 'border-success/50 bg-success/5' : 'border-outline-variant bg-white' }} p-5 shadow-sm">
                    <div class="flex min-h-[92px] items-start justify-between gap-4">
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            @if($exam['icon_url'])
                                <img src="{{ $exam['icon_url'] }}" alt="Icon" class="size-11 shrink-0 rounded-xl object-cover border border-outline-variant">
                            @else
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary text-white">
                                    <span class="material-symbols-outlined text-[24px] text-white">quiz</span>
                                </span>
                            @endif
                            <div class="min-w-0">
                                <h2 class="text-lg font-bold text-on-surface line-clamp-2">{{ $exam['title'] }}</h2>
                                <p class="mt-1 text-sm leading-6 text-on-surface-variant line-clamp-2">{{ $exam['description'] }}</p>
                            </div>
                        </div>
                        @if ($locked)
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800 shrink-0">Premium</span>
                        @endif
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                        <div class="flex min-h-[84px] flex-col justify-between rounded-xl bg-surface-container-low p-3">
                            <p class="text-xs font-bold text-on-surface-variant">Số câu</p>
                            <p class="mt-1 text-2xl font-bold text-on-surface">{{ $exam['question_count'] }}</p>
                        </div>
                        <div class="flex min-h-[84px] flex-col justify-between rounded-xl bg-surface-container-low p-3">
                            <p class="text-xs font-bold text-on-surface-variant">Thời gian</p>
                            <p class="mt-1 text-2xl font-bold text-on-surface">{{ $exam['duration_minutes'] }}'</p>
                        </div>
                    </div>

                    @if ($session)
                        <div class="mt-4 flex items-center justify-between rounded-xl {{ $isCompleted ? 'bg-success/10 text-success' : 'bg-primary/10 text-primary' }} px-4 py-3 text-sm font-bold">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[20px]">{{ $isCompleted ? 'check_circle' : 'pending' }}</span>
                                <span>{{ $isCompleted ? 'Đã xong' : 'Chưa xong' }} ({{ $session->answered_count }}/{{ $session->total }})</span>
                            </div>
                            @if ($isCompleted)
                                <a href="{{ route('exam.summary', $session) }}" class="underline hover:text-success/80">Xem kết quả</a>
                            @endif
                        </div>
                    @endif

                    <div class="mt-5 flex flex-1 flex-col gap-3">
                        <div class="mt-auto">
                            @if ($locked)
                                <a href="{{ route('billing.plans') }}"
                                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-primary/30 px-4 text-sm font-bold text-primary hover:bg-primary/5">
                                    <span class="material-symbols-outlined text-[18px]">lock</span>
                                    Nâng cấp để bắt đầu
                                </a>
                            @elseif ($isActive)
                                <a href="{{ route('exam.session', $session) }}"
                                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-bold text-white transition-colors hover:bg-primary/90">
                                    <span class="material-symbols-outlined text-[18px]">play_arrow</span>
                                    Tiếp tục
                                </a>
                            @else
                                <form method="POST" action="{{ route('exam.start', $exam['id']) }}" class="w-full">
                                    @csrf
                                    <button type="submit" @disabled($exam['question_count'] == 0)
                                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:bg-outline {{ $isCompleted ? 'bg-surface-container-highest text-on-surface hover:bg-surface-container-highest/80' : 'bg-primary' }}">
                                        @if($isCompleted)
                                            <span class="material-symbols-outlined text-[18px] text-on-surface">refresh</span>
                                            <span class="text-on-surface">Làm lại</span>
                                        @else
                                            <span class="material-symbols-outlined text-[18px]">play_arrow</span>
                                            {{ $exam['question_count'] == 0 ? 'Đề trống' : 'Bắt đầu thi' }}
                                        @endif
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-outline-variant bg-white px-6 py-12 text-center">
                    <p class="font-bold text-on-surface">Chưa có đề thi nào.</p>
                </div>
            @endforelse
        </div>

        @if ($examCards->hasPages())
            <div class="mt-8">
                {{ $examCards->links() }}
            </div>
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
                    <p class="mt-2 text-sm text-on-surface-variant">Bắt đầu một đề mô phỏng để lưu lịch sử tại đây.</p>
                </div>
            @else
                <div class="overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-sm">
                    @foreach ($recentSessions as $session)
                        @php
                            $examId = is_array($session->filters) ? ($session->filters['exam_id'] ?? null) : null;
                            $examTitle = $examId ? \Modules\Exam\Models\Exam::find($examId)?->title : 'Kỳ thi';
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
