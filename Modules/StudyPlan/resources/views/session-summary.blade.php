@php
    /**
     * @var \Modules\StudyPlan\Models\StudyPlan $plan
     * @var \Modules\StudyPlan\Models\StudyPlanTask $task
     * @var list<array<string, mixed>> $topics
     * @var list<array<string, mixed>> $questionOverview
     */
    $minutes = intdiv(max(0, $timeSpentSeconds), 60);
    $seconds = max(0, $timeSpentSeconds) % 60;
    $timeLabel = $minutes > 0
        ? sprintf('%d phút %02d giây', $minutes, $seconds)
        : sprintf('%d giây', $seconds);
    $summaryConfig = $summaryConfig ?? [
        'page_title' => 'Phân tích kết quả',
        'heading' => 'Phân tích kết quả',
        'subtitle' => $task->title() . ' · hoàn thành ' . $total . ' câu hỏi trong phiên này.',
        'breadcrumbs' => [
            ['label' => 'Kế hoạch học tập', 'url' => route('study-plan.index')],
            ['label' => $plan->name, 'url' => route('study-plan.detail', $plan)],
            ['label' => 'Phân tích', 'url' => null],
        ],
        'review_url' => route('study-plan.session.review', [$plan, $task]),
        'back_url' => route('study-plan.detail', $plan),
        'back_label' => 'Quay lại kế hoạch',
        'back_icon' => 'route',
        'progress_label' => $task->done . '/' . $task->target . ' mục tiêu nhiệm vụ',
        'context_message' => 'Tiến độ kế hoạch đã được cập nhật sau nhiệm vụ này.',
    ];
    $reviewUrl = $summaryConfig['review_url'];
    $detailUrl = $summaryConfig['back_url'];
    $lessonProgress = $lessonProgress ?? [];
    $correctWithHintCount = $correctWithHintCount ?? 0;
    $hintAccuracy = $total > 0 ? (int) round($correctWithHintCount / $total * 100) : 0;
    $questionTimeLabel = static function (int $totalSeconds): string {
        $totalSeconds = max(0, $totalSeconds);

        return sprintf('%d phút %d giây', intdiv($totalSeconds, 60), $totalSeconds % 60);
    };
    $questionPageSize = 5;
    $questionPageCount = max(1, (int) ceil(count($questionOverview) / $questionPageSize));
    $questionPage = min($questionPageCount, max(1, request()->integer('question_page', 1)));
    $questionPageRows = array_slice(
        $questionOverview,
        ($questionPage - 1) * $questionPageSize,
        $questionPageSize,
    );
    $questionPageUrl = static fn (int $page): string => request()
        ->fullUrlWithQuery(['question_page' => $page]).'#question-overview';
@endphp

<x-layouts.app :title="$summaryConfig['page_title']">
    <div class="mx-auto max-w-6xl space-y-8 p-4 md:p-8">
        <div>
            <nav class="mb-2 flex flex-wrap gap-2 text-xs text-on-surface-variant">
                @foreach ($summaryConfig['breadcrumbs'] as $breadcrumb)
                    @if (! $loop->first)
                        <span>/</span>
                    @endif
                    @if ($breadcrumb['url'])
                        <a href="{{ $breadcrumb['url'] }}" class="hover:text-primary">{{ $breadcrumb['label'] }}</a>
                    @else
                        <span class="font-medium text-primary">{{ $breadcrumb['label'] }}</span>
                    @endif
                @endforeach
            </nav>
            <h1 class="font-headline-lg text-headline-lg text-on-surface">{{ $summaryConfig['heading'] }}</h1>
            <p class="mt-1 font-body-md text-on-surface-variant">
                {{ $summaryConfig['subtitle'] }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <div
                class="flex flex-col items-center gap-8 rounded-2xl border border-outline-variant bg-white p-6 shadow-sm md:flex-row md:p-8 lg:col-span-8">
                <div class="donut-chart size-48 shrink-0" style="background: {{ $donutStyle }}">
                    <div class="donut-inner">
                        <div class="text-center">
                            <span class="block text-4xl font-extrabold text-primary">{{ $accuracy }}%</span>
                            <span class="text-xs font-semibold text-on-surface-variant">ĐÚNG</span>
                        </div>
                    </div>
                </div>
                <div class="w-full flex-1">
                    <div class="mb-6 grid grid-cols-3 gap-4">
                        <div class="text-center md:text-left">
                            <p class="mb-1 text-xs font-bold text-on-surface-variant uppercase">Đúng</p>
                            <p class="text-2xl font-bold text-[#16A34A]">{{ $correctCount }}</p>
                            <p class="mt-1 text-xs font-semibold text-[#CA8A04]" data-testid="hint-correct-rate">
                                {{ $hintAccuracy }}% có gợi ý
                            </p>
                        </div>
                        <div class="text-center md:text-left">
                            <p class="mb-1 text-xs font-bold text-on-surface-variant uppercase">Sai</p>
                            <p class="text-2xl font-bold text-error">{{ $wrongCount }}</p>
                        </div>
                        <div class="text-center md:text-left">
                            <p class="mb-1 text-xs font-bold text-on-surface-variant uppercase">Bỏ qua</p>
                            <p class="text-2xl font-bold text-outline">{{ $skippedCount }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 border-t border-outline-variant pt-6">
                        <div class="flex items-center gap-2 rounded-lg bg-surface-container px-3 py-1.5">
                            <span class="material-symbols-outlined text-sm text-on-surface-variant">timer</span>
                            <span class="text-sm font-medium">{{ $timeLabel }}</span>
                        </div>
                        @if ($flaggedCount > 0)
                            <div class="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-1.5 text-amber-700">
                                <span class="material-symbols-outlined text-sm"
                                    style="font-variation-settings: 'FILL' 1;">flag</span>
                                <span class="text-sm font-medium">{{ $flaggedCount }} câu gắn cờ</span>
                            </div>
                        @endif
                        <div class="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary-container/10 px-3 py-1.5">
                            <span class="material-symbols-outlined text-sm text-primary">task_alt</span>
                            <span class="text-sm font-bold text-primary">
                                {{ $summaryConfig['progress_label'] }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-outline-variant bg-white p-6 shadow-sm lg:col-span-4">
                <h2 class="mb-4 font-headline-sm text-headline-sm">Tóm tắt nhanh</h2>
                <ul class="space-y-4 text-body-sm text-on-surface-variant">
                    <li class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-primary">insights</span>
                        <span>
                            Bạn trả lời đúng <strong class="text-on-surface">{{ $accuracy }}%</strong> số câu trong phiên.
                        </span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="material-symbols-outlined {{ $wrongCount > 0 ? 'text-error' : 'text-[#16A34A]' }}">
                            {{ $wrongCount > 0 ? 'priority_high' : 'verified' }}
                        </span>
                        <span>
                            @if ($wrongCount > 0)
                                Có <strong class="text-on-surface">{{ $wrongCount }}</strong> câu sai —
                                nên xem lại giải thích.
                            @else
                                Không có câu sai trong phiên này.
                            @endif
                        </span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-on-surface-variant">route</span>
                        <span>{{ $summaryConfig['context_message'] }}</span>
                    </li>
                </ul>
            </div>

            <div class="overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-sm lg:col-span-12"
                x-data="{ analysisTab: 'session' }" data-testid="lesson-analysis-tabs">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant">
                    <div class="flex min-w-0 flex-1 overflow-x-auto" role="tablist" aria-label="Phân tích bài">
                        <button type="button" role="tab" id="analysis-tab-session"
                            @click="analysisTab = 'session'"
                            :aria-selected="analysisTab === 'session'"
                            aria-controls="analysis-panel-session"
                            class="shrink-0 border-b-2 px-6 py-4 text-sm font-bold transition-colors"
                            :class="analysisTab === 'session'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-on-surface-variant hover:text-on-surface'">
                            Phân tích bài theo phiên
                        </button>
                        <button type="button" role="tab" id="analysis-tab-process"
                            @click="analysisTab = 'process'"
                            :aria-selected="analysisTab === 'process'"
                            aria-controls="analysis-panel-process"
                            class="shrink-0 border-b-2 px-6 py-4 text-sm font-bold transition-colors"
                            :class="analysisTab === 'process'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-on-surface-variant hover:text-on-surface'">
                            Đề xuất học tập
                        </button>
                    </div>
                    <a href="{{ $reviewUrl }}" x-show="analysisTab === 'session'"
                        class="mr-6 flex items-center gap-1 text-sm font-bold text-primary hover:underline">
                        Xem từng câu
                        <span class="material-symbols-outlined text-base">chevron_right</span>
                    </a>
                </div>

                <div id="analysis-panel-session" role="tabpanel" aria-labelledby="analysis-tab-session"
                    x-show="analysisTab === 'session'">

                @if ($topics === [])
                    <p class="p-6 text-body-sm text-on-surface-variant">Chưa có dữ liệu chủ đề để phân tích.</p>
                @else
                    <div class="hidden md:block">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="bg-surface-container-low text-on-surface-variant">
                                    <th class="px-6 py-4 text-xs font-bold tracking-wider uppercase">Chủ đề</th>
                                    <th class="px-6 py-4 text-xs font-bold tracking-wider uppercase">Tỷ lệ đúng</th>
                                    <th class="px-6 py-4 text-xs font-bold tracking-wider uppercase">Số câu</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold tracking-wider uppercase">Đánh giá</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                @foreach ($topics as $topic)
                                    <tr class="{{ $topic['rowClass'] }}">
                                        <td class="px-6 py-4">
                                            <div class="font-bold {{ $topic['nameClass'] }}">{{ $topic['name'] }}</div>
                                        </td>
                                        <td class="w-1/3 px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-container">
                                                    <div class="h-full {{ $topic['barClass'] }}"
                                                        style="width: {{ $topic['rate'] }}%"></div>
                                                </div>
                                                <span class="text-sm font-bold {{ $topic['rateClass'] }}">
                                                    {{ $topic['rate'] }}%
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium">{{ $topic['count'] }}</td>
                                        <td class="px-6 py-4 text-right">
                                            @if ($topic['reviewUrl'])
                                                <a href="{{ $topic['reviewUrl'] }}"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-error px-3 py-1.5 text-sm font-bold text-white transition-opacity hover:opacity-90">
                                                    {{ $topic['actionLabel'] }}
                                                    <span class="material-symbols-outlined text-base">chevron_right</span>
                                                </a>
                                            @else
                                                <span class="inline-flex rounded-lg px-3 py-1.5 text-sm font-bold text-primary">
                                                    {{ $topic['actionLabel'] }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-outline-variant md:hidden">
                        @foreach ($topics as $topic)
                            <div class="space-y-3 p-4 {{ $topic['rowClass'] }}">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold {{ $topic['nameClass'] }}">{{ $topic['name'] }}</span>
                                    <span class="text-sm font-medium">{{ $topic['count'] }} câu</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-container">
                                        <div class="h-full {{ $topic['barClass'] }}"
                                            style="width: {{ $topic['rate'] }}%"></div>
                                    </div>
                                    <span class="text-xs font-bold {{ $topic['rateClass'] }}">{{ $topic['rate'] }}%</span>
                                </div>
                                @if ($topic['reviewUrl'])
                                    <a href="{{ $topic['reviewUrl'] }}"
                                        class="inline-flex items-center gap-1 rounded-lg bg-error px-3 py-1.5 text-sm font-bold text-white transition-opacity hover:opacity-90">
                                        {{ $topic['actionLabel'] }}
                                        <span class="material-symbols-outlined text-base">chevron_right</span>
                                    </a>
                                @else
                                    <span
                                        class="inline-flex rounded-lg border border-outline-variant px-3 py-1.5 text-sm font-bold text-primary">
                                        {{ $topic['actionLabel'] }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
                </div>

                <div id="analysis-panel-process" role="tabpanel" aria-labelledby="analysis-tab-process"
                    x-show="analysisTab === 'process'" x-cloak x-data="{ openLesson: null }" data-testid="lesson-progress">
                    @if ($lessonProgress === [])
                        <p class="p-6 text-body-sm text-on-surface-variant">Chưa có bài để đề xuất.</p>
                    @else
                        <div class="divide-y divide-outline-variant">
                            @foreach ($lessonProgress as $lesson)
                                <div>
                                    <div class="flex items-end gap-3 px-6 py-4 hover:bg-surface-container-low/60">
                                    <button type="button"
                                        class="flex min-w-0 flex-1 items-center gap-3 text-left"
                                        @click="openLesson = openLesson === {{ $lesson['lesson_id'] }} ? null : {{ $lesson['lesson_id'] }}"
                                        :aria-expanded="openLesson === {{ $lesson['lesson_id'] }}"
                                        aria-controls="lesson-detail-{{ $lesson['lesson_id'] }}">
                                        <span class="material-symbols-outlined text-[20px] text-on-surface-variant transition-transform"
                                            :class="openLesson === {{ $lesson['lesson_id'] }} ? 'rotate-90' : ''"
                                            aria-hidden="true">chevron_right</span>
                                        @php
                                            $hintCount = min($lesson['correct_with_hint'], $lesson['correct']);
                                            $unaidedCount = $lesson['correct'] - $hintCount;
                                            $latestAccuracy = $lesson['graded'] > 0
                                                ? (int) round($lesson['correct'] / $lesson['graded'] * 100)
                                                : 0;
                                        @endphp
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate font-bold text-on-surface">{{ $lesson['name'] }}</span>
                                            <span class="mt-2 flex items-center gap-3">
                                                <span class="flex h-1.5 flex-1 overflow-hidden rounded-full bg-surface-container"
                                                    title="{{ $unaidedCount }} đúng, {{ $hintCount }} đúng có gợi ý, {{ $lesson['wrong'] }} sai, {{ $lesson['not_done'] }} chưa làm">
                                                    <span class="h-full bg-[#16A34A]" style="width: {{ $lesson['unaided_share'] }}%"></span>
                                                    <span class="h-full bg-[#FDE68A]" style="width: {{ $lesson['hint_share'] }}%"></span>
                                                    <span class="h-full bg-error" style="width: {{ $lesson['wrong_share'] }}%"></span>
                                                </span>
                                                <span class="shrink-0 text-xs font-bold text-on-surface">
                                                    {{ $lesson['graded'] > 0 ? $latestAccuracy.'% đúng' : 'Chưa làm' }}
                                                </span>
                                            </span>
                                        </span>
                                    </button>
                                    @if ($lesson['needs_review'])
                                        <form method="POST" action="{{ route('qbank.weak-topics.session', $lesson['lesson_id']) }}" class="shrink-0">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex items-center gap-1 rounded-lg bg-error px-3 py-1.5 text-sm font-bold text-white transition-opacity hover:opacity-90">
                                                Cần ôn
                                                <span class="material-symbols-outlined text-base">chevron_right</span>
                                            </button>
                                        </form>
                                    @endif
                                    </div>
                                    <div id="lesson-detail-{{ $lesson['lesson_id'] }}" x-show="openLesson === {{ $lesson['lesson_id'] }}" x-cloak
                                        class="space-y-4 px-6 pb-4 pl-14">
                                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                                            <div>
                                                <dt class="text-xs text-on-surface-variant">Tổng số câu</dt>
                                                <dd class="font-bold text-on-surface" data-testid="lesson-{{ $lesson['lesson_id'] }}-total">{{ $lesson['total'] }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs text-on-surface-variant">Câu đúng</dt>
                                                <dd class="font-bold text-[#16A34A]" data-testid="lesson-{{ $lesson['lesson_id'] }}-correct">{{ $lesson['correct'] }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs text-on-surface-variant">Đúng có gợi ý</dt>
                                                <dd class="font-bold text-[#CA8A04]" data-testid="lesson-{{ $lesson['lesson_id'] }}-hint">{{ $lesson['correct_with_hint'] }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs text-on-surface-variant">Câu sai</dt>
                                                <dd class="font-bold text-error" data-testid="lesson-{{ $lesson['lesson_id'] }}-wrong">{{ $lesson['wrong'] }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs text-on-surface-variant">Chưa làm</dt>
                                                <dd class="font-bold text-on-surface" data-testid="lesson-{{ $lesson['lesson_id'] }}-not-done">{{ $lesson['not_done'] }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-sm lg:col-span-12"
                id="question-overview" data-testid="question-overview-table">
                <div class="flex items-center justify-between border-b border-outline-variant p-6">
                    <div>
                        <h2 class="font-headline-sm text-headline-sm">Tổng quan từng câu</h2>
                        <p class="mt-1 text-xs text-on-surface-variant">
                            Thống kê đồng nghiệp tính theo kết quả gần nhất của từng người dùng.
                        </p>
                    </div>
                    <a href="{{ $reviewUrl }}" class="hidden items-center gap-1 text-sm font-bold text-primary hover:underline sm:flex">
                        Xem chi tiết
                        <span class="material-symbols-outlined text-base">chevron_right</span>
                    </a>
                </div>

                @if ($questionOverview === [])
                    <p class="p-6 text-body-sm text-on-surface-variant">Chưa có dữ liệu từng câu để thống kê.</p>
                @else
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full min-w-[980px] border-collapse text-left">
                            <thead>
                                <tr class="bg-surface-container-low text-on-surface-variant">
                                    <th class="px-5 py-4 text-xs font-bold tracking-wider uppercase">Câu hỏi</th>
                                    <th class="px-5 py-4 text-xs font-bold tracking-wider uppercase">Kết quả</th>
                                    <th class="px-5 py-4 text-xs font-bold tracking-wider uppercase">
                                        <span class="inline-flex items-center gap-1">
                                            Thời gian cho mỗi câu hỏi
                                            <span class="material-symbols-outlined text-[15px]">unfold_more</span>
                                        </span>
                                    </th>
                                    <th class="px-5 py-4 text-xs font-bold tracking-wider uppercase">
                                        <span class="inline-flex items-center gap-1">
                                            Thống kê đồng nghiệp
                                            <span class="material-symbols-outlined text-[15px]">unfold_more</span>
                                        </span>
                                    </th>
                                    <th class="px-5 py-4 text-xs font-bold tracking-wider uppercase">
                                        <span class="inline-flex items-center gap-1">
                                            Khó khăn
                                            <span class="material-symbols-outlined text-[15px]">unfold_more</span>
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                @foreach ($questionPageRows as $row)
                                    @php
                                        $resultLabel = match ($row['result']) {
                                            'correct' => 'Đúng',
                                            'wrong' => 'Sai',
                                            default => 'Bỏ qua',
                                        };
                                        $resultClass = match ($row['result']) {
                                            'correct' => 'bg-[#16A34A]/10 text-[#16A34A]',
                                            'wrong' => 'bg-error/10 text-error',
                                            default => 'bg-surface-container-high text-outline',
                                        };
                                    @endphp
                                    <tr class="hover:bg-surface-container-low/60">
                                        <td class="max-w-xs px-5 py-4">
                                            <div class="flex items-start gap-3">
                                                <span class="font-bold text-primary">{{ $row['id'] }}</span>
                                                <span class="line-clamp-2 text-sm leading-relaxed text-on-surface"
                                                    title="{{ $row['excerpt'] }}">{{ $row['excerpt'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $resultClass }}">
                                                {{ $resultLabel }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-sm whitespace-nowrap text-on-surface">
                                            {{ $questionTimeLabel((int) $row['time_spent_seconds']) }}
                                        </td>
                                        <td class="px-5 py-4 text-sm font-medium text-on-surface"
                                            title="{{ $row['peer_users'] }} người dùng đã làm câu này">
                                            {{ $row['peer_accuracy'] !== null ? $row['peer_accuracy'].'%' : 'Chưa có dữ liệu' }}
                                        </td>
                                        <td class="px-5 py-4 text-sm font-medium text-on-surface-variant">
                                            {{ $row['difficulty'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-outline-variant md:hidden">
                        @foreach ($questionPageRows as $row)
                            @php
                                $resultLabel = match ($row['result']) {
                                    'correct' => 'Đúng',
                                    'wrong' => 'Sai',
                                    default => 'Bỏ qua',
                                };
                                $resultClass = match ($row['result']) {
                                    'correct' => 'text-[#16A34A]',
                                    'wrong' => 'text-error',
                                    default => 'text-outline',
                                };
                            @endphp
                            <div class="space-y-4 p-4">
                                <div class="flex items-start gap-3">
                                    <span class="font-bold text-primary">{{ $row['id'] }}</span>
                                    <span class="line-clamp-2 text-sm leading-relaxed text-on-surface">{{ $row['excerpt'] }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-xs">
                                    <div>
                                        <p class="font-bold tracking-wide text-on-surface-variant uppercase">Kết quả</p>
                                        <p class="mt-1 font-bold {{ $resultClass }}">{{ $resultLabel }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold tracking-wide text-on-surface-variant uppercase">Thời gian</p>
                                        <p class="mt-1 text-on-surface">{{ $questionTimeLabel((int) $row['time_spent_seconds']) }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold tracking-wide text-on-surface-variant uppercase">Đồng nghiệp</p>
                                        <p class="mt-1 text-on-surface">
                                            {{ $row['peer_accuracy'] !== null ? $row['peer_accuracy'].'% trả lời đúng' : 'Chưa có dữ liệu' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="font-bold tracking-wide text-on-surface-variant uppercase">Khó khăn</p>
                                        <p class="mt-1 font-medium text-on-surface">{{ $row['difficulty'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($questionPageCount > 1)
                        <nav class="flex flex-col items-center justify-between gap-3 border-t border-outline-variant px-4 py-4 sm:flex-row"
                            aria-label="Phân trang tổng quan từng câu" data-testid="question-overview-pagination">
                            <p class="text-xs text-on-surface-variant">
                                Hiển thị
                                {{ ($questionPage - 1) * $questionPageSize + 1 }}–{{ min($questionPage * $questionPageSize, count($questionOverview)) }}
                                / {{ count($questionOverview) }} câu
                            </p>
                            <div class="flex items-center gap-1">
                                @if ($questionPage > 1)
                                    <a href="{{ $questionPageUrl($questionPage - 1) }}"
                                        class="flex size-9 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-low"
                                        aria-label="Trang trước">
                                        <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                                    </a>
                                @endif

                                @for ($page = 1; $page <= $questionPageCount; $page++)
                                    <a href="{{ $questionPageUrl($page) }}"
                                        class="flex size-9 items-center justify-center rounded-lg text-sm font-bold {{ $page === $questionPage ? 'bg-primary text-white' : 'border border-outline-variant text-on-surface-variant hover:bg-surface-container-low' }}"
                                        aria-current="{{ $page === $questionPage ? 'page' : 'false' }}">
                                        {{ $page }}
                                    </a>
                                @endfor

                                @if ($questionPage < $questionPageCount)
                                    <a href="{{ $questionPageUrl($questionPage + 1) }}"
                                        class="flex size-9 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container-low"
                                        aria-label="Trang sau">
                                        <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                                    </a>
                                @endif
                            </div>
                        </nav>
                    @endif
                @endif
            </div>
        </div>

        <div class="flex flex-col items-stretch justify-between gap-3 sm:flex-row sm:items-center">
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ $reviewUrl }}"
                    class="flex items-center justify-center gap-2 rounded-xl bg-primary-container px-6 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110 active:scale-95">
                    <span class="material-symbols-outlined">rate_review</span>
                    Xem lại từng câu
                    @if ($wrongCount > 0)
                        <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ $wrongCount }} sai</span>
                    @endif
                </a>
                <a href="{{ $detailUrl }}"
                    class="flex items-center justify-center gap-2 rounded-xl border border-outline-variant bg-white px-6 py-3 font-bold text-on-surface-variant transition-all hover:bg-surface-container-low active:scale-95">
                    <span class="material-symbols-outlined">{{ $summaryConfig['back_icon'] }}</span>
                    {{ $summaryConfig['back_label'] }}
                </a>
                @if (! empty($summaryConfig['retry_url']))
                    <form method="POST" action="{{ $summaryConfig['retry_url'] }}" class="w-full sm:w-auto">
                        @csrf
                        <button type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 font-bold text-white shadow-lg transition-all hover:brightness-110 active:scale-95">
                            <span class="material-symbols-outlined">refresh</span>
                            Làm lại
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
