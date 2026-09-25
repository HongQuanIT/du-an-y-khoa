<x-layouts.reviewer title="Tổng quan" :pending-count="$pendingCount">
    <x-admin.page-header title="Tổng quan reviewer" description="Theo dõi hàng đợi, kết quả gắn cờ và công việc cần ưu tiên.">
        <x-slot:actions><span class="inline-flex items-center gap-1.5 rounded-full border border-outline-variant bg-surface px-3 py-1.5 font-label-sm text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">update</span>Cập nhật {{ $refreshedAt->format('H:i d/m/Y') }}</span></x-slot:actions>
    </x-admin.page-header>
    <x-admin.flash />
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpis as $kpi)
            <x-admin.kpi-card :label="$kpi['label']" :value="is_numeric($kpi['value']) ? number_format($kpi['value']) : $kpi['value']" :hint="$kpi['hint']" :icon="$kpi['icon']" :severity="$kpi['severity']" />
        @endforeach
    </div>
    <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2" data-admin-dashboard-charts data-charts='@json($charts)'>
        @foreach ($charts as $chart)
            <x-admin.trend-chart :id="$chart['id']" :title="$chart['title']" :subtitle="$chart['subtitle']" />
        @endforeach
    </div>
    <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-outline-variant bg-surface p-5" aria-labelledby="reviewer-todos-heading">
            <header class="mb-4"><h2 id="reviewer-todos-heading" class="font-headline-sm text-on-surface">Việc cần xử lý</h2><p class="mt-1 font-body-sm text-on-surface-variant">Ưu tiên các câu hỏi đang chờ lâu.</p></header>
            <ul class="space-y-3">
                @foreach ($todos as $todo)
                    <li class="rounded-lg border border-outline-variant">
                        @if ($todo['href'])<a href="{{ $todo['href'] }}" class="flex items-center gap-3 p-4 transition hover:bg-surface-container-low">@else<div class="flex items-center gap-3 p-4">@endif
                            <span @class(['flex size-10 shrink-0 items-center justify-center rounded-full text-on-primary', 'bg-amber-600' => $todo['severity'] === 'warning', 'bg-success' => $todo['severity'] === 'ok', 'bg-primary' => $todo['severity'] === 'info'])><span class="material-symbols-outlined text-[20px]">{{ $todo['icon'] }}</span></span>
                            <span class="min-w-0 flex-1"><span class="block font-label-md text-on-surface">{{ $todo['title'] }}</span><span class="mt-1 block font-body-sm text-on-surface-variant">{{ $todo['description'] }}</span></span>
                            @if ($todo['href'])<span class="material-symbols-outlined text-on-surface-variant">chevron_right</span>@endif
                        @if ($todo['href'])</a>@else</div>@endif
                    </li>
                @endforeach
            </ul>
        </section>
        @if ($canViewQuestions)
            <section class="rounded-xl border border-outline-variant bg-surface p-5" aria-labelledby="reviewer-queue-heading">
                <header class="mb-4 flex items-start justify-between gap-3"><div><h2 id="reviewer-queue-heading" class="font-headline-sm text-on-surface">Câu hỏi cần review</h2><p class="mt-1 font-body-sm text-on-surface-variant">Câu hỏi chờ lâu nhất được hiển thị trước.</p></div><a href="{{ route('reviewer.questions.flags.index') }}" class="shrink-0 font-label-md font-semibold text-primary">Xem tất cả →</a></header>
                @forelse ($priorityQuestions as $question)
                    <a href="{{ route('reviewer.questions.flags.show', $question) }}" class="flex items-start gap-3 border-t border-outline-variant py-3 transition hover:bg-surface-container-low"><span class="material-symbols-outlined mt-1 text-primary">quiz</span><span class="min-w-0 flex-1"><span class="block font-label-sm font-semibold text-primary">{{ $question->code }}</span><span class="mt-0.5 block line-clamp-2 font-label-md text-on-surface">{{ \Illuminate\Support\Str::limit(strip_tags($question->stem), 110) }}</span><span class="mt-1 block font-body-sm text-on-surface-variant">{{ $question->lessons->pluck('name')->join(', ') ?: 'Chưa có bài học' }} · {{ $question->difficulty->label() }} · {{ $question->creator?->name ?? 'Không rõ người soạn' }} · {{ $question->updated_at->diffForHumans() }}</span></span><span class="material-symbols-outlined mt-2 text-on-surface-variant">chevron_right</span></a>
                @empty
                    <p class="border-t border-outline-variant py-5 font-body-sm text-on-surface-variant">Hiện không có câu hỏi chờ review.</p>
                @endforelse
            </section>
        @endif
    </div>
    @if ($canViewQuestions)
        <section class="mb-8 rounded-xl border border-outline-variant bg-surface p-5" aria-labelledby="reviewer-history-heading">
            <header class="mb-4 flex items-start justify-between gap-3"><div><h2 id="reviewer-history-heading" class="font-headline-sm text-on-surface">Review gần đây</h2><p class="mt-1 font-body-sm text-on-surface-variant">Các lượt gắn cờ mới nhất của bạn.</p></div><a href="{{ route('reviewer.questions.flags.index', ['tab' => 'done']) }}" class="shrink-0 font-label-md font-semibold text-primary">Xem lịch sử →</a></header>
            @forelse ($recentFlags as $flag)
                <div class="flex items-center gap-3 border-t border-outline-variant py-3"><span @class(['material-symbols-outlined', 'text-emerald-700' => $flag->flag === \Modules\QuestionBank\Enums\ReviewerFlag::Green, 'text-red-600' => $flag->flag === \Modules\QuestionBank\Enums\ReviewerFlag::Red])>{{ $flag->flag === \Modules\QuestionBank\Enums\ReviewerFlag::Green ? 'check_circle' : 'cancel' }}</span><div class="min-w-0 flex-1"><p class="font-label-md text-on-surface">{{ $flag->question?->code ?? 'Câu hỏi đã xóa' }} · {{ $flag->flag->label() }}</p><p class="truncate font-body-sm text-on-surface-variant">{{ $flag->question ? \Illuminate\Support\Str::limit(strip_tags($flag->question->stem), 100) : 'Không còn nội dung' }}</p></div><time datetime="{{ $flag->reviewed_at?->toIso8601String() }}" class="shrink-0 font-label-sm text-on-surface-variant">{{ $flag->reviewed_at?->diffForHumans() }}</time></div>
            @empty
                <p class="border-t border-outline-variant py-5 font-body-sm text-on-surface-variant">Bạn chưa có lượt review nào.</p>
            @endforelse
        </section>
    @endif
    <x-admin.quick-actions :actions="array_values(array_filter([
        $canViewQuestions ? ['label' => 'Hàng đợi review', 'href' => route('reviewer.questions.flags.index'), 'icon' => 'flag'] : null,
        $canViewQuestions ? ['label' => 'Đã gắn cờ', 'href' => route('reviewer.questions.flags.index', ['tab' => 'done']), 'icon' => 'task_alt'] : null,
        auth()->user()->can('reviewer_notification.view') ? ['label' => 'Thông báo', 'href' => route('reviewer.notifications.index'), 'icon' => 'notifications'] : null,
    ]))" />
    @push('scripts')
        @vite('resources/js/admin/dashboard-charts.js')
    @endpush
</x-layouts.reviewer>
