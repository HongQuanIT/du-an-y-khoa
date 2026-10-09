@php
    $isSample = $exam->kind === 'sample';
    $quotas = $exam->matrix_snapshot['difficulty_quotas'] ?? [];
    $questionsById = $exam->questions->keyBy(fn ($question) => (string) $question->getKey());
    $topicNamesById = $exam->examTopics
        ->mapWithKeys(fn ($examTopic) => [
            (int) $examTopic->core_clinical_topic_id => $examTopic->coreClinicalTopic?->name,
        ]);
    $difficultyLabels = [
        'very_easy' => 'Rất dễ',
        'easy' => 'Dễ',
        'medium' => 'Trung bình',
        'hard' => 'Khó',
        'very_hard' => 'Rất khó',
    ];
@endphp

<x-layouts.admin :title="'Chi tiết bài thi — '.$exam->title">
    <div class="space-y-6">
        <nav aria-label="Đường dẫn trang" class="flex items-center gap-2 text-sm text-on-surface-variant">
            <a href="{{ route('admin.exams.index') }}" class="rounded-md text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">Bài thi</a>
            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">chevron_right</span>
            <span aria-current="page">Chi tiết bài thi</span>
        </nav>

        <header class="flex flex-col gap-4 border-b border-outline-variant pb-6 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-semibold text-primary">{{ $isSample ? 'Bài mẫu' : ($exam->kind === 'personal' ? 'Đề cá nhân' : 'Đề cũ') }}</span>
                    <span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-800' => $exam->isPublished(), 'bg-amber-50 text-amber-800' => ! $exam->isPublished()])>{{ $exam->status?->label() ?? '—' }}</span>
                </div>
                <h1 class="max-w-4xl break-words font-headline-md text-headline-md font-bold tracking-tight text-on-surface [text-wrap:balance]">{{ $exam->title }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-on-surface-variant [text-wrap:pretty]">{{ $isSample ? 'Bộ câu hỏi mẫu dùng chung, cố định theo ma trận tại thời điểm tạo.' : 'Bài thi được tạo cho học viên từ ma trận đề thi.' }}</p>
            </div>
            <a href="{{ route('admin.exams.index') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 self-start rounded-xl border border-outline-variant bg-surface px-4 text-sm font-semibold text-on-surface shadow-sm transition-[background-color,border-color] duration-150 hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                <span class="material-symbols-outlined text-[19px]" aria-hidden="true">arrow_back</span>
                Danh sách bài thi
            </a>
        </header>

        <x-admin.flash />

    @if ($isSample)
        <section aria-labelledby="sample-heading" class="rounded-2xl border border-primary/20 bg-primary/5 p-4 sm:p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 id="sample-heading" class="flex items-center gap-2 text-base font-semibold text-on-surface"><span class="material-symbols-outlined text-[21px] text-primary" aria-hidden="true">verified</span>Bài thi mẫu dùng chung</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">Bộ câu hỏi và đáp án được giữ cố định.</p>
                    @if ($matrixChanged)
                        <p role="status" class="mt-3 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm leading-relaxed text-amber-900"><span class="material-symbols-outlined mt-0.5 text-[18px]" aria-hidden="true">warning</span><span>Ma trận đã thay đổi. Bài mẫu này vẫn giữ bộ câu cũ; hãy tạo bản mẫu mới để áp dụng ma trận hiện tại.</span></p>
                    @endif
                </div>
                @can('blueprint.update')
                    @if (!$exam->isPublished())
                        <form method="POST" action="{{ route('admin.exams.publish-sample', $exam) }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-on-primary shadow-sm transition-[background-color,transform] duration-150 hover:bg-primary/90 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"><span class="material-symbols-outlined text-[19px]" aria-hidden="true">publish</span>Xuất bản bài mẫu</button>
                        </form>
                    @endif
                @endcan
            </div>
            <div class="mt-4 flex flex-wrap gap-2 border-t border-primary/15 pt-4 text-xs font-medium tabular-nums text-on-surface-variant">
                <span class="rounded-lg bg-surface px-3 py-2">Dễ: <strong class="text-on-surface">{{ (int) ($quotas['easy'] ?? 0) }}</strong></span>
                <span class="rounded-lg bg-surface px-3 py-2">Trung bình: <strong class="text-on-surface">{{ (int) ($quotas['medium'] ?? 0) }}</strong></span>
                <span class="rounded-lg bg-surface px-3 py-2">Khó: <strong class="text-on-surface">{{ (int) ($quotas['hard'] ?? 0) }}</strong></span>
            </div>
        </section>
    @endif
    <section aria-label="Thông tin tổng quan bài thi" class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm sm:p-5">
            <p class="flex items-center gap-2 text-sm font-medium text-on-surface-variant"><span class="material-symbols-outlined text-[19px]" aria-hidden="true">quiz</span>Số câu</p>
            <p class="mt-3 text-2xl font-bold leading-none tabular-nums text-on-surface">{{ number_format($exam->questionCount()) }}</p>
        </div>
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm sm:p-5">
            <p class="flex items-center gap-2 text-sm font-medium text-on-surface-variant"><span class="material-symbols-outlined text-[19px]" aria-hidden="true">schedule</span>Thời gian</p>
            <p class="mt-3 text-2xl font-bold leading-none tabular-nums text-on-surface">{{ number_format($exam->duration_minutes) }} <span class="text-sm font-medium text-on-surface-variant">phút</span></p>
        </div>
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm sm:p-5">
            <p class="flex items-center gap-2 text-sm font-medium text-on-surface-variant"><span class="material-symbols-outlined text-[19px]" aria-hidden="true">category</span>Chủ đề lâm sàng</p>
            <p class="mt-3 text-2xl font-bold leading-none tabular-nums text-on-surface">{{ number_format($exam->examTopics->count()) }}</p>
        </div>
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm sm:p-5">
            <p class="flex items-center gap-2 text-sm font-medium text-on-surface-variant"><span class="material-symbols-outlined text-[19px]" aria-hidden="true">event</span>Ngày tạo</p>
            <p class="mt-3 text-lg font-bold leading-none tabular-nums text-on-surface"><time datetime="{{ $exam->created_at?->toIso8601String() }}">{{ $exam->created_at?->format('d/m/Y') ?? '—' }}</time></p>
            @if ($exam->created_at)<p class="mt-1 text-xs tabular-nums text-on-surface-variant">{{ $exam->created_at->format('H:i') }}</p>@endif
        </div>
    </section>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
        <main class="min-w-0 space-y-6">
            <section aria-labelledby="topics-heading" class="overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-sm">
                <div class="border-b border-outline-variant px-5 py-4 sm:px-6">
                    <h2 id="topics-heading" class="text-base font-semibold text-on-surface">Phân bổ câu hỏi theo chủ đề</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">Số câu trong từng phần và nhóm độ khó.</p>
                </div>
                <div class="overflow-x-auto" tabindex="0" aria-label="Vùng cuộn bảng phân bổ câu hỏi">
                    <table class="w-full min-w-[680px] border-collapse text-sm text-on-surface">
                        <caption class="sr-only">Phân bổ câu hỏi theo chủ đề, phần và mức độ khó</caption>
                        <thead class="bg-surface-container-low text-left text-xs font-semibold uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th scope="col" class="px-5 py-3.5 sm:px-6">Chủ đề</th>
                                <th scope="col" class="px-4 py-3.5">Phần</th>
                                <th scope="col" class="px-3 py-3.5 text-center">Số câu</th>
                                <th scope="col" class="px-3 py-3.5 text-center">Dễ</th>
                                <th scope="col" class="px-3 py-3.5 text-center">Trung bình</th>
                                <th scope="col" class="px-3 py-3.5 text-center">Khó</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/60">
                            @forelse ($exam->examTopics as $topic)
                                <tr class="transition-colors duration-150 hover:bg-surface-container-low/70">
                                    <td class="px-5 py-3.5 font-semibold text-on-surface [text-wrap:pretty] sm:px-6">{{ $topic->coreClinicalTopic?->name ?? '#' . $topic->core_clinical_topic_id }}</td>
                                    <td class="px-4 py-3.5 text-on-surface-variant">{{ $topic->coreClinicalTopic?->section?->name ?? '—' }}</td>
                                    <td class="px-3 py-3.5 text-center font-semibold tabular-nums">{{ $topic->question_count }}</td>
                                    <td class="px-3 py-3.5 text-center tabular-nums">{{ ($topic->difficulty_counts['very_easy'] ?? 0) + ($topic->difficulty_counts['easy'] ?? 0) }}</td>
                                    <td class="px-3 py-3.5 text-center tabular-nums">{{ $topic->difficulty_counts['medium'] ?? 0 }}</td>
                                    <td class="px-3 py-3.5 text-center tabular-nums">{{ ($topic->difficulty_counts['hard'] ?? 0) + ($topic->difficulty_counts['very_hard'] ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center text-on-surface-variant">Bài thi này chưa có phân bổ theo chủ đề lâm sàng.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <aside aria-label="Thông tin liên quan" class="space-y-4">
            <section aria-labelledby="learner-heading" class="rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
                <h2 id="learner-heading" class="text-sm font-semibold text-on-surface">Học viên</h2>
                @if ($exam->user)
                    <p class="mt-3 font-semibold text-on-surface">{{ $exam->user->name }}</p>
                    <p class="mt-1 break-all text-sm text-on-surface-variant">{{ $exam->user->email }}</p>
                @else
                    <p class="mt-3 text-sm leading-relaxed text-on-surface-variant">{{ $isSample ? 'Bài mẫu dùng chung cho học viên của kỳ thi.' : 'Không gắn học viên.' }}</p>
                @endif
            </section>

            <section aria-labelledby="blueprint-heading" class="rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
                <h2 id="blueprint-heading" class="text-sm font-semibold text-on-surface">Ma trận đề thi</h2>
                @if ($exam->blueprint)
                    <p class="mt-3 font-semibold leading-snug text-on-surface [text-wrap:pretty]">{{ $exam->blueprint->name }}</p>
                    @if ($exam->blueprint->code)
                        <p class="mt-1 break-all font-mono text-xs text-on-surface-variant">{{ $exam->blueprint->code }}</p>
                    @endif
                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.blueprints.edit'))
                        <a href="{{ route('admin.blueprints.edit', $exam->blueprint) }}"
                            class="mt-4 inline-flex min-h-10 items-center gap-1.5 rounded-lg text-sm font-semibold text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                            Xem ma trận
                            <span class="material-symbols-outlined text-[17px]" aria-hidden="true">open_in_new</span>
                        </a>
                    @endif
                @else
                    <p class="mt-3 text-sm text-on-surface-variant">Chưa gắn ma trận</p>
                @endif
            </section>
        </aside>
    </div>
    @if ($exam->paper_snapshot)
        <section aria-label="Bộ câu hỏi cố định">
            <details class="group overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-sm">
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-base font-semibold text-on-surface transition-colors duration-150 hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/40 [&::-webkit-details-marker]:hidden sm:px-6">
                    <span>Bộ câu hỏi và đáp án cố định <span class="ml-1 text-sm font-medium tabular-nums text-on-surface-variant">({{ count($exam->paper_snapshot) }} câu)</span></span>
                    <span class="material-symbols-outlined shrink-0 text-[20px] text-on-surface-variant transition-transform duration-150 group-open:rotate-180" aria-hidden="true">expand_more</span>
                </summary>
                <ol class="divide-y divide-outline-variant border-t border-outline-variant">
                    @foreach ($exam->paper_snapshot as $row)
                        @php
                            $payload = $row['payload'] ?? [];
                            $question = $questionsById->get((string) ($row['question_id'] ?? ''));
                            $topicName = $question
                                ? $topicNamesById->get((int) $question->pivot->core_clinical_topic_id)
                                : null;
                            $difficulty = $payload['difficulty'] ?? null;
                            $lessonNames = collect($payload['lessons'] ?? [])
                                ->pluck('name')
                                ->merge($payload['lesson_names'] ?? [])
                                ->filter()
                                ->unique()
                                ->values()
                                ->all();
                        @endphp
                        <li class="px-5 py-5 sm:px-6">
                            <p class="text-xs font-semibold uppercase tracking-wide text-primary">Câu {{ $loop->iteration }}</p>
                            <dl class="mt-2 flex flex-wrap gap-2 text-xs">
                                <div class="inline-flex items-center gap-1.5 rounded-lg bg-surface-container-low px-2.5 py-1.5 text-on-surface-variant">
                                    <dt class="font-medium">Độ khó:</dt>
                                    <dd class="font-semibold text-on-surface">{{ $difficultyLabels[$difficulty] ?? '—' }}</dd>
                                </div>
                                <div class="inline-flex min-w-0 items-center gap-1.5 rounded-lg bg-surface-container-low px-2.5 py-1.5 text-on-surface-variant">
                                    <dt class="shrink-0 font-medium">Bài học:</dt>
                                    <dd class="font-semibold text-on-surface [text-wrap:pretty]">{{ $lessonNames ? implode(', ', $lessonNames) : '—' }}</dd>
                                </div>
                                <div class="inline-flex min-w-0 items-center gap-1.5 rounded-lg bg-surface-container-low px-2.5 py-1.5 text-on-surface-variant">
                                    <dt class="shrink-0 font-medium">Chủ đề:</dt>
                                    <dd class="font-semibold text-on-surface [text-wrap:pretty]">{{ $topicName ?: '—' }}</dd>
                                </div>
                            </dl>
                            <p class="mt-3 font-medium leading-relaxed text-on-surface [text-wrap:pretty]">{{ strip_tags($payload['stem'] ?? '') }}</p>
                            @if (!empty($payload['options']))
                                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                                    @foreach ($payload['options'] as $option)
                                        <li @class([
                                            'rounded-xl border px-3 py-2 text-sm leading-relaxed [text-wrap:pretty]',
                                            'border-primary/30 bg-primary/5 font-semibold text-primary' => $option['is_correct'] ?? false,
                                            'border-outline-variant text-on-surface-variant' => !($option['is_correct'] ?? false),
                                        ])>
                                            {{ $option['label'] ?? '' }}. {{ strip_tags($option['content'] ?? '') }}
                                            @if ($option['is_correct'] ?? false)<span class="sr-only">Đáp án đúng</span><span aria-hidden="true">✓</span>@endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </details>
        </section>
    @endif
    </div>
</x-layouts.admin>
