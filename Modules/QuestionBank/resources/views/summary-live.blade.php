@php
    /**
     * @var \Modules\QuestionBank\Models\QuestionSession $session
     * @var array<string, mixed> $summary
     */
    $seconds = max(0, (int) $summary['time_spent_seconds']);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingTimeSeconds = $seconds % 60;
    $timeLabel = $hours > 0
        ? sprintf('%d giờ %02d phút', $hours, $minutes)
        : ($minutes > 0 ? sprintf('%d phút %02d giây', $minutes, $remainingTimeSeconds) : sprintf('%d giây', $remainingTimeSeconds));
    $isExam = $session->mode->value === 'exam';
    $reviewUrl = route('qbank.review', $session);
@endphp

<x-layouts.app title="Tổng kết phiên luyện">
    <div class="mx-auto max-w-6xl space-y-8 p-4 md:p-8">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <nav class="mb-2 flex flex-wrap items-center gap-2 text-xs text-on-surface-variant">
                    <a href="{{ route('qbank.index') }}" class="hover:text-primary">Ngân hàng câu hỏi</a>
                    <span>/</span>
                    <span>Phiên {{ Str::limit((string) $session->id, 8, '') }}</span>
                    <span>/</span>
                    <span class="font-bold text-primary">Tổng kết</span>
                </nav>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Tổng kết phiên {{ $isExam ? 'thi' : 'luyện tập' }}</h1>
                <p class="mt-1 text-body-sm text-on-surface-variant">
                    Bạn đã hoàn thành {{ $summary['total'] }} câu hỏi · {{ $isExam ? 'Exam mode' : 'Study mode' }}.
                </p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-success/10 px-4 py-2 text-sm font-bold text-success">
                <span class="material-symbols-outlined text-[20px]">task_alt</span>
                Đã hoàn thành
            </span>
        </header>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <section class="flex flex-col items-center gap-8 rounded-2xl border border-outline-variant bg-white p-6 shadow-sm md:flex-row md:p-8 lg:col-span-8">
                <div class="relative flex size-48 shrink-0 items-center justify-center rounded-full"
                    style="background: {{ $summary['donut_style'] }}">
                    <div class="flex size-[72%] flex-col items-center justify-center rounded-full bg-white shadow-inner">
                        <span class="text-4xl font-extrabold text-primary">{{ $summary['accuracy'] }}%</span>
                        <span class="mt-1 text-xs font-bold tracking-wider text-on-surface-variant uppercase">Chính xác</span>
                    </div>
                </div>
                <div class="w-full flex-1">
                    <div class="grid grid-cols-3 divide-x divide-outline-variant">
                        <div class="px-2 text-center md:px-4 md:text-left">
                            <p class="text-xs font-bold text-on-surface-variant uppercase">Đúng</p>
                            <p class="mt-1 text-3xl font-extrabold text-success">{{ $summary['correct'] }}</p>
                        </div>
                        <div class="px-2 text-center md:px-4 md:text-left">
                            <p class="text-xs font-bold text-on-surface-variant uppercase">Sai</p>
                            <p class="mt-1 text-3xl font-extrabold text-error">{{ $summary['wrong'] }}</p>
                        </div>
                        <div class="px-2 text-center md:px-4 md:text-left">
                            <p class="text-xs font-bold text-on-surface-variant uppercase">Bỏ qua</p>
                            <p class="mt-1 text-3xl font-extrabold text-outline">{{ $summary['skipped'] }}</p>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-3 border-t border-outline-variant pt-6">
                        <span class="inline-flex items-center gap-2 rounded-lg bg-surface-container px-3 py-2 text-sm font-semibold text-on-surface-variant">
                            <span class="material-symbols-outlined text-[19px]">timer</span>
                            {{ $timeLabel }}
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-lg bg-surface-container px-3 py-2 text-sm font-semibold text-on-surface-variant">
                            <span class="material-symbols-outlined text-[19px]">done_all</span>
                            {{ $summary['answered'] }}/{{ $summary['total'] }} đã trả lời
                        </span>
                        @if ($summary['flagged'] > 0)
                            <a href="{{ route('qbank.review', [$session, 'filter' => 'flagged']) }}"
                                class="inline-flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100">
                                <span class="material-symbols-outlined text-[19px]" style="font-variation-settings: 'FILL' 1;">flag</span>
                                {{ $summary['flagged'] }} gắn cờ
                            </a>
                        @endif
                    </div>
                </div>
            </section>

            <aside class="rounded-2xl border border-outline-variant bg-white p-6 shadow-sm lg:col-span-4">
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Bước tiếp theo</h2>
                <div class="mt-5 space-y-3">
                    @if ($summary['wrong'] > 0)
                        <a href="{{ route('qbank.review', [$session, 'filter' => 'wrong']) }}"
                            class="flex items-center justify-between gap-3 rounded-xl border border-error/20 bg-error/5 p-4 transition-colors hover:bg-error/10">
                            <span class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-error">error</span>
                                <span>
                                    <span class="block text-sm font-bold text-on-surface">Xem {{ $summary['wrong'] }} câu sai</span>
                                    <span class="mt-0.5 block text-xs text-on-surface-variant">Đọc lại giải thích từng đáp án</span>
                                </span>
                            </span>
                            <span class="material-symbols-outlined text-error">chevron_right</span>
                        </a>
                    @endif
                    <a href="{{ $reviewUrl }}"
                        class="flex items-center justify-between gap-3 rounded-xl border border-outline-variant p-4 transition-colors hover:border-primary/40 hover:bg-primary/5">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-primary">rate_review</span>
                            <span>
                                <span class="block text-sm font-bold text-on-surface">Xem lại toàn bộ</span>
                                <span class="mt-0.5 block text-xs text-on-surface-variant">Đáp án, ghi chú và câu gắn cờ</span>
                            </span>
                        </span>
                        <span class="material-symbols-outlined text-primary">chevron_right</span>
                    </a>
                    <a href="{{ route('qbank.create', ['mode' => 'study', 'question_statuses' => ['incorrect']]) }}"
                        class="flex items-center justify-between gap-3 rounded-xl border border-outline-variant p-4 transition-colors hover:border-primary/40 hover:bg-primary/5">
                        <span class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-primary">replay</span>
                            <span>
                                <span class="block text-sm font-bold text-on-surface">Tạo phiên ôn câu sai</span>
                                <span class="mt-0.5 block text-xs text-on-surface-variant">Luyện lại từ lịch sử gần nhất</span>
                            </span>
                        </span>
                        <span class="material-symbols-outlined text-primary">chevron_right</span>
                    </a>
                </div>
            </aside>

            <section class="overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-sm lg:col-span-12">
                <div class="flex flex-col justify-between gap-3 border-b border-outline-variant p-5 sm:flex-row sm:items-center md:p-6">
                    <div>
                        <h2 class="font-headline-sm text-headline-sm text-on-surface">Phân tích theo chủ đề</h2>
                        <p class="mt-1 text-xs text-on-surface-variant">Tỷ lệ tính trên toàn bộ câu trong chủ đề, gồm cả câu bỏ qua.</p>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-on-surface-variant">
                        <span class="size-3 rounded bg-success"></span> Tốt
                        <span class="ml-2 size-3 rounded bg-error"></span> Cần ôn
                    </div>
                </div>

                @if ($summary['topics'] === [])
                    <div class="p-8 text-center">
                        <span class="material-symbols-outlined text-5xl text-outline-variant">query_stats</span>
                        <p class="mt-3 text-body-sm text-on-surface-variant">Chưa có dữ liệu chủ đề để phân tích.</p>
                    </div>
                @else
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full border-collapse text-left">
                            <thead class="bg-surface-container-low text-xs font-bold tracking-wider text-on-surface-variant uppercase">
                                <tr>
                                    <th class="px-6 py-4">Chủ đề</th>
                                    <th class="px-6 py-4">Tỷ lệ đúng</th>
                                    <th class="px-6 py-4">Kết quả</th>
                                    <th class="px-6 py-4 text-right">Đánh giá</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                @foreach ($summary['topics'] as $topic)
                                    @php
                                        $rateColor = $topic['rate'] < 60 ? 'bg-error' : ($topic['rate'] < 75 ? 'bg-warning' : 'bg-success');
                                        $textColor = $topic['rate'] < 60 ? 'text-error' : ($topic['rate'] < 75 ? 'text-warning' : 'text-success');
                                    @endphp
                                    <tr>
                                        <td class="px-6 py-5 font-bold text-on-surface">{{ $topic['name'] }}</td>
                                        <td class="w-2/5 px-6 py-5">
                                            <div class="flex items-center gap-3">
                                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-container">
                                                    <div class="h-full rounded-full {{ $rateColor }}" style="width: {{ $topic['rate'] }}%"></div>
                                                </div>
                                                <span class="w-12 text-right text-sm font-bold {{ $textColor }}">{{ $topic['rate'] }}%</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5 text-sm font-semibold text-on-surface-variant">
                                            {{ $topic['correct'] }} đúng · {{ $topic['wrong'] }} sai · {{ $topic['skipped'] }} bỏ
                                        </td>
                                        <td class="px-6 py-5 text-right">
                                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $topic['rate'] < 60 ? 'bg-error/10 text-error' : 'bg-success/10 text-success' }}">
                                                {{ $topic['rate'] < 60 ? 'Cần ôn lại' : 'Đang tiến bộ' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-outline-variant md:hidden">
                        @foreach ($summary['topics'] as $topic)
                            @php
                                $rateColor = $topic['rate'] < 60 ? 'bg-error' : ($topic['rate'] < 75 ? 'bg-warning' : 'bg-success');
                                $textColor = $topic['rate'] < 60 ? 'text-error' : ($topic['rate'] < 75 ? 'text-warning' : 'text-success');
                            @endphp
                            <div class="space-y-3 p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-bold text-on-surface">{{ $topic['name'] }}</span>
                                    <span class="text-sm font-bold {{ $textColor }}">{{ $topic['rate'] }}%</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-surface-container">
                                    <div class="h-full rounded-full {{ $rateColor }}" style="width: {{ $topic['rate'] }}%"></div>
                                </div>
                                <p class="text-xs text-on-surface-variant">
                                    {{ $topic['correct'] }} đúng · {{ $topic['wrong'] }} sai · {{ $topic['skipped'] }} bỏ qua
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <div class="flex flex-col gap-3 border-t border-outline-variant pt-6 sm:flex-row sm:justify-between">
            <a href="{{ route('qbank.index') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-outline-variant bg-white px-6 py-3 font-bold text-on-surface-variant transition-colors hover:bg-surface-container-low">
                <span class="material-symbols-outlined">history</span>
                Lịch sử phiên luyện
            </a>
            <a href="{{ route('qbank.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 font-bold text-white shadow-md transition-all hover:brightness-110">
                Tạo phiên mới <span class="material-symbols-outlined">arrow_forward</span>
            </a>
        </div>
    </div>
</x-layouts.app>
