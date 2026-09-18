<x-layouts.admin :title="'Bài thi: '.$exam->title">
    <div class="mb-6">
        <a href="{{ route('admin.exams.index') }}"
            class="inline-flex items-center gap-1.5 font-label-sm text-primary hover:underline">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Danh sách bài thi
        </a>
        <h1 class="mt-2 font-headline-md text-headline-md text-on-surface">{{ $exam->title }}</h1>
        <p class="mt-1 font-body-sm text-on-surface-variant">Chỉ xem — bài thi do học viên tạo từ ma trận.</p>
    </div>

    <x-admin.flash />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <main class="space-y-6">
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-xl border border-outline-variant bg-surface px-4 py-4">
                    <p class="font-label-sm text-on-surface-variant">Số câu</p>
                    <p class="mt-1 font-headline-sm text-on-surface">{{ $exam->questions_count }}</p>
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
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($exam->examTopics as $topic)
                                <tr class="border-t border-outline-variant/60">
                                    <td class="px-4 py-2.5 font-label-md text-on-surface">{{ $topic->coreClinicalTopic?->name ?? '#' . $topic->core_clinical_topic_id }}</td>
                                    <td class="px-4 py-2.5 text-on-surface-variant">{{ $topic->coreClinicalTopic?->section?->name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-center font-semibold">{{ $topic->question_count }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-on-surface-variant">Không có phân bổ CCT.</td>
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
                    <p class="mt-3 font-body-sm text-on-surface-variant">Không gắn học viên (dữ liệu cũ).</p>
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
