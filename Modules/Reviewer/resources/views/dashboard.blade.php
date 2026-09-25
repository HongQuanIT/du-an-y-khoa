<x-layouts.reviewer title="Tổng quan" :pending-count="$pendingCount">
    <x-admin.page-header title="Tổng quan reviewer" description="Theo dõi hàng đợi và câu hỏi bạn đã review." />
    <x-admin.flash />
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-admin.kpi-card label="Chờ gắn cờ" :value="number_format($pendingCount)" hint="Câu hỏi đang chờ bạn review" icon="flag" severity="warning" />
        <x-admin.kpi-card label="Đã gắn cờ" :value="number_format($doneCount)" hint="Câu hỏi bạn đã xử lý" icon="task_alt" severity="ok" />
    </div>
    <section class="rounded-xl border border-outline-variant bg-surface p-5">
        <div class="mb-4 flex items-center justify-between gap-3"><div><h2 class="font-headline-sm">Câu hỏi cần review</h2><p class="mt-1 text-sm text-on-surface-variant">Ưu tiên các câu hỏi đang chờ gắn cờ.</p></div><a href="{{ route('reviewer.questions.flags.index') }}" class="font-label-md font-semibold text-primary">Xem tất cả →</a></div>
        @forelse ($questions as $question)
            <a href="{{ route('reviewer.questions.flags.show', $question) }}" class="block border-t border-outline-variant py-3 text-primary">{{ $question->code }} · {{ \Illuminate\Support\Str::limit(strip_tags($question->stem), 90) }}</a>
        @empty
            <p class="text-on-surface-variant">Hiện không có câu hỏi chờ review.</p>
        @endforelse
    </section>
</x-layouts.reviewer>
