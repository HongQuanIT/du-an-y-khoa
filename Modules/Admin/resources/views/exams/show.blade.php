<x-layouts.admin :title="'Bài thi: '.$exam->title">
    <div class="mb-6">
        <a href="{{ route('admin.exams.index') }}"
            class="inline-flex items-center gap-1.5 font-label-sm text-primary hover:underline">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Danh sách bài thi
        </a>
        <h1 class="mt-2 font-headline-md text-headline-md text-on-surface">{{ $exam->title }}</h1>
        <p class="mt-1 font-body-sm text-on-surface-variant">{{ $exam->kind === 'sample' ? 'Bài thi mẫu do Admin tạo từ ma trận.' : 'Chỉ xem — bài thi cá nhân từ ma trận.' }}</p>
    </div>

    <x-admin.flash />

    @if ($exam->kind === 'sample')
        <div class="mb-6 rounded-xl border border-primary/20 bg-primary/5 p-4">
            <p class="font-bold">Bài thi mẫu dùng chung · Bộ câu và đáp án cố định</p>
            @if ($matrixChanged)
                <p class="mt-2 text-sm text-amber-800">Ma trận đã thay đổi. Đề này giữ nguyên bộ câu cũ; tạo bản mẫu mới để áp dụng ma trận hiện tại.</p>
            @endif
            <p class="mt-2 text-sm">Dễ: {{ $exam->matrix_snapshot['difficulty_quotas']['easy'] ?? 0 }} · Trung bình: {{ $exam->matrix_snapshot['difficulty_quotas']['medium'] ?? 0 }} · Khó: {{ $exam->matrix_snapshot['difficulty_quotas']['hard'] ?? 0 }}</p>
            @can('blueprint.update')
                @if (!$exam->isPublished())
                    <form method="POST" action="{{ route('admin.exams.publish-sample', $exam) }}" class="mt-3">
                        @csrf
                        <button class="rounded-lg bg-primary px-4 py-2 text-white">Xuất bản bài thi mẫu</button>
                    </form>
                @endif
            @endcan
        </div>
    @endif
    @if ($exam->paper_snapshot)
        <details class="mb-6 rounded-xl border border-outline-variant p-4">
            <summary class="cursor-pointer font-bold">Xem bộ câu và đáp án cố định ({{ count($exam->paper_snapshot) }} câu)</summary>
            @foreach ($exam->paper_snapshot as $row)
                <div class="mt-4 border-t border-outline-variant pt-4">
                    <p class="font-bold">Câu {{ $loop->iteration }}</p>
                    <div>{{ strip_tags($row['payload']['stem']) }}</div>
                    @foreach ($row['payload']['options'] ?? [] as $option)
                        <p class="mt-1 {{ ($option['is_correct'] ?? false) ? 'font-bold text-primary' : '' }}">{{ $option['label'] ?? '' }}. {{ strip_tags($option['content'] ?? '') }} {{ ($option['is_correct'] ?? false) ? '✓' : '' }}</p>
                    @endforeach
                </div>
            @endforeach
        </details>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <main class="space-y-6">
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-xl border border-outline-variant bg-surface px-4 py-4">
                    <p class="font-label-sm text-on-surface-variant">Số câu</p>
                    <p class="mt-1 font-headline-sm text-on-surface">{{ $exam->questionCount() }}</p>
                </div>
                <div class="rounded-xl border border-outline-variant bg-surface px-4 py-4">
                    <p class="font-label-sm text-on-surface-variant">Thời gian</p>
                    <p class="mt-1 font-headline-sm text-on-surface">{{ $exam->duration_minutes }} phút</p>
                </div>
                <div class="rounded-xl border border-outline-variant bg-surface px-4 py-4">
                    <p class="font-label-sm text-on-surface-variant">Chủ đề CCT</p>
                    <p class="mt-1 font-headline-sm text-on-surface">{{ $exam->examTopics->count() }}</p>
                </div>
                <div class="rounded-xl border border-outline-variant bg-surface px-4 py-4">
                    <p class="font-label-sm text-on-surface-variant">Trạng thái</p>
                    <p class="mt-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $exam->isPublished() ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant' }}">
                            {{ $exam->status?->label() ?? '—' }}
                        </span>
                    </p>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
                <div class="border-b border-outline-variant px-5 py-4">
                    <h2 class="font-label-lg text-on-surface">Phân bổ theo chủ đề</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-left text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-2">Chủ đề</th>
                                <th class="px-4 py-2">Phần</th>
                                <th class="px-4 py-2 text-center">Số câu</th>
                                <th class="px-4 py-2 text-center">Dễ</th>
                                <th class="px-4 py-2 text-center">Trung bình</th>
                                <th class="px-4 py-2 text-center">Khó</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($exam->examTopics as $topic)
                                <tr class="border-t border-outline-variant/60">
                                    <td class="px-4 py-2.5 font-label-md text-on-surface">{{ $topic->coreClinicalTopic?->name ?? '#' . $topic->core_clinical_topic_id }}</td>
                                    <td class="px-4 py-2.5 text-on-surface-variant">{{ $topic->coreClinicalTopic?->section?->name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-center font-semibold">{{ $topic->question_count }}</td>
                                    <td class="px-4 py-2.5 text-center">{{ ($topic->difficulty_counts['very_easy'] ?? 0) + ($topic->difficulty_counts['easy'] ?? 0) }}</td>
                                    <td class="px-4 py-2.5 text-center">{{ $topic->difficulty_counts['medium'] ?? 0 }}</td>
                                    <td class="px-4 py-2.5 text-center">{{ ($topic->difficulty_counts['hard'] ?? 0) + ($topic->difficulty_counts['very_hard'] ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-on-surface-variant">Không có phân bổ CCT.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <aside class="space-y-4">
            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <h2 class="font-label-lg text-on-surface">Học viên</h2>
                @if ($exam->user)
                    <p class="mt-3 font-label-md text-on-surface">{{ $exam->user->name }}</p>
                    <p class="mt-1 font-label-sm text-on-surface-variant">{{ $exam->user->email }}</p>
                @else
                    <p class="mt-3 font-body-sm text-on-surface-variant">{{ $exam->kind === 'sample' ? 'Dùng chung cho học viên thuộc chức danh của kỳ thi.' : 'Không gắn học viên (dữ liệu cũ).' }}</p>
                @endif
                <p class="mt-4 font-label-sm text-on-surface-variant">Tạo lúc {{ $exam->created_at?->format('d/m/Y H:i') }}</p>
            </section>

            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <h2 class="font-label-lg text-on-surface">Kỳ thi (ma trận)</h2>
                @if ($exam->blueprint)
                    <p class="mt-3 font-label-md text-on-surface">{{ $exam->blueprint->name }}</p>
                    @if ($exam->blueprint->code)
                        <p class="mt-1 font-mono text-xs text-on-surface-variant">{{ $exam->blueprint->code }}</p>
                    @endif
                    @if (\Modules\Admin\Support\AdminRouteAccess::allows(auth()->user(), 'admin.blueprints.edit'))
                        <a href="{{ route('admin.blueprints.edit', $exam->blueprint) }}"
                            class="mt-4 inline-flex items-center gap-1 font-label-sm text-primary hover:underline">
                            Xem ma trận
                            <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                        </a>
                    @endif
                @else
                    <p class="mt-3 font-body-sm text-on-surface-variant">—</p>
                @endif
            </section>
        </aside>
    </div>
</x-layouts.admin>
