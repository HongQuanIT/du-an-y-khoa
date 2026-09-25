<x-layouts.reviewer title="Tổng quan">
    <x-admin.page-header title="Tổng quan reviewer" description="Theo dõi hàng đợi và câu hỏi bạn đã review." />
    <x-admin.flash />
    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <a href="{{ route('reviewer.questions.flags.index') }}" class="rounded-xl border border-outline-variant bg-surface p-5">
            <p class="text-on-surface-variant">Chờ gắn cờ</p><p class="mt-2 text-3xl font-bold text-primary">{{ $pendingCount }}</p>
        </a>
        <a href="{{ route('reviewer.questions.flags.index', ['tab' => 'done']) }}" class="rounded-xl border border-outline-variant bg-surface p-5">
            <p class="text-on-surface-variant">Đã gắn cờ</p><p class="mt-2 text-3xl font-bold text-primary">{{ $doneCount }}</p>
        </a>
    </div>
    <section class="rounded-xl border border-outline-variant bg-surface p-5">
        <h2 class="mb-4 font-headline-sm">Câu hỏi cần review</h2>
        @forelse ($questions as $question)
            <a href="{{ route('reviewer.questions.flags.show', $question) }}" class="block border-t border-outline-variant py-3 text-primary">{{ $question->code }} · {{ \Illuminate\Support\Str::limit(strip_tags($question->stem), 90) }}</a>
        @empty
            <p class="text-on-surface-variant">Hiện không có câu hỏi chờ review.</p>
        @endforelse
    </section>
</x-layouts.reviewer>
