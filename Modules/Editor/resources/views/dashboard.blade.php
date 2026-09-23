<x-layouts.editor title="Tổng quan">
    <x-admin.page-header title="Bảng điều khiển biên tập" description="Theo dõi tiến độ soạn, duyệt và xuất bản câu hỏi của bạn.">
        <x-slot:actions>
            @can('question.create')
                <a href="{{ route('editor.questions.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-label-md text-label-md text-on-primary transition hover:opacity-90"><span class="material-symbols-outlined text-[18px]">add</span>Tạo câu hỏi mới</a>
            @endcan
            <span class="inline-flex items-center gap-1.5 rounded-full border border-outline-variant bg-surface px-3 py-1.5 font-label-sm text-label-sm text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">update</span>Cập nhật {{ $refreshed_at->format('H:i d/m/Y') }}</span>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpis as $kpi)
            <x-admin.kpi-card :label="$kpi['label']" :value="number_format($kpi['value'])" :hint="$kpi['hint']" :icon="$kpi['icon']" :severity="$kpi['severity']" />
        @endforeach
    </div>

    <section class="mb-8 rounded-xl border border-outline-variant bg-surface p-5" aria-labelledby="editor-quick-actions-heading">
        <header class="mb-4">
            <h2 id="editor-quick-actions-heading" class="font-headline-sm text-headline-sm text-on-surface">Thao tác nhanh</h2>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">Mở nhanh các công việc biên tập thường dùng.</p>
        </header>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @can('question.create')
                <a href="{{ route('editor.questions.create') }}" class="inline-flex items-center gap-3 rounded-lg border border-outline-variant px-4 py-3 font-label-md text-label-md text-on-surface transition hover:border-primary hover:text-primary"><span class="material-symbols-outlined text-primary">add_circle</span>Tạo câu hỏi mới</a>
            @endcan
            @can('question.import')
                <a href="{{ route('editor.questions.import') }}" class="inline-flex items-center gap-3 rounded-lg border border-outline-variant px-4 py-3 font-label-md text-label-md text-on-surface transition hover:border-primary hover:text-primary"><span class="material-symbols-outlined text-primary">upload_file</span>Import câu hỏi</a>
            @endcan
            @can('question.export')
                <a href="{{ route('editor.questions.export') }}" class="inline-flex items-center gap-3 rounded-lg border border-outline-variant px-4 py-3 font-label-md text-label-md text-on-surface transition hover:border-primary hover:text-primary"><span class="material-symbols-outlined text-primary">download</span>Export câu hỏi</a>
            @endcan
            @can('taxonomy.view')
                <a href="{{ route('editor.taxonomy.index') }}" class="inline-flex items-center gap-3 rounded-lg border border-outline-variant px-4 py-3 font-label-md text-label-md text-on-surface transition hover:border-primary hover:text-primary"><span class="material-symbols-outlined text-primary">account_tree</span>Phân loại kiến thức</a>
            @endcan
        </div>
    </section>

    <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2" data-admin-dashboard-charts data-charts='@json($charts)'>
        @foreach ($charts as $chart)
            <x-admin.trend-chart :id="$chart['id']" :title="$chart['title']" :subtitle="$chart['subtitle']" />
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-outline-variant bg-surface p-5" aria-labelledby="editor-todos-heading">
            <header class="mb-4"><h2 id="editor-todos-heading" class="font-headline-sm text-headline-sm text-on-surface">Việc cần xử lý</h2><p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">Ưu tiên hoàn thiện các nội dung đang chờ bạn.</p></header>
            <ul class="space-y-3">
                @foreach ($todos as $todo)
                    <li class="flex gap-3 rounded-lg border border-outline-variant p-4">
                        <span @class(['flex size-10 shrink-0 items-center justify-center rounded-full text-on-primary', 'bg-error' => $todo['severity'] === 'critical', 'bg-amber-600' => $todo['severity'] === 'warning', 'bg-primary' => $todo['severity'] === 'info', 'bg-success' => $todo['severity'] === 'ok'])><span class="material-symbols-outlined text-[21px]">{{ $todo['icon'] }}</span></span>
                        <span><span class="block font-label-md text-label-md text-on-surface">{{ $todo['title'] }}</span><span class="mt-1 block font-body-sm text-body-sm text-on-surface-variant">{{ $todo['description'] }}</span></span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-xl border border-outline-variant bg-surface p-5" aria-labelledby="editor-recent-heading">
            <header class="mb-4"><h2 id="editor-recent-heading" class="font-headline-sm text-headline-sm text-on-surface">Nội dung cập nhật gần đây</h2><p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">6 câu hỏi mới được bạn thay đổi.</p></header>
            @if (count($recent_questions) === 0)
                <p class="font-body-sm text-body-sm text-on-surface-variant">Chưa có câu hỏi nào để hiển thị.</p>
            @else
                <ol class="divide-y divide-outline-variant">
                    @foreach ($recent_questions as $question)
                        @php($updatedAt = \Illuminate\Support\Carbon::parse($question['updated_at']))
                        <li class="py-3 first:pt-0 last:pb-0"><article class="flex items-start gap-3"><span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary text-on-primary"><span class="material-symbols-outlined text-[19px]">quiz</span></span><div class="min-w-0"><p class="font-label-sm text-label-sm text-primary">{{ $question['code'] }}</p><h3 class="mt-0.5 line-clamp-2 font-label-md text-label-md text-on-surface">{{ $question['title'] ?: 'Câu hỏi chưa có nội dung' }}</h3><p class="mt-1 font-label-sm text-label-sm text-on-surface-variant">{{ $question['status']->label() }} · <time datetime="{{ $updatedAt->toIso8601String() }}">{{ $updatedAt->diffForHumans() }}</time></p></div></article></li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    @push('scripts')
        @vite('resources/js/admin/dashboard-charts.js')
    @endpush
</x-layouts.editor>
