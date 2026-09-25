<x-layouts.reviewer title="Review câu hỏi">
    <x-admin.page-header title="Review câu hỏi" description="Đọc nội dung và gắn cờ câu hỏi đã qua duyệt chuyên môn." />
    <x-admin.flash />
    <nav class="mb-5 flex gap-2" aria-label="Trạng thái hàng đợi review">
        <a href="{{ route('reviewer.questions.flags.index', ['tab' => 'pending']) }}" @class(['rounded-lg px-3 py-2', 'bg-primary text-on-primary' => $tab === 'pending', 'bg-surface-container-low' => $tab !== 'pending'])>Chờ gắn cờ ({{ $stats['pending'] }})</a>
        <a href="{{ route('reviewer.questions.flags.index', ['tab' => 'done']) }}" @class(['rounded-lg px-3 py-2', 'bg-primary text-on-primary' => $tab === 'done', 'bg-surface-container-low' => $tab !== 'done'])>Đã gắn cờ ({{ $stats['done'] }})</a>
    </nav>
    <form method="get" class="mb-4 flex gap-2">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input name="q" value="{{ request('q') }}" placeholder="Tìm mã hoặc nội dung câu hỏi" class="w-full max-w-md rounded-lg border border-outline-variant bg-surface px-3 py-2">
        <button class="rounded-lg bg-primary px-4 py-2 text-on-primary">Tìm</button>
    </form>
    <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface">
        @forelse ($questions as $question)
            <a href="{{ route('reviewer.questions.flags.show', $question) }}" class="flex items-center justify-between gap-4 border-b border-outline-variant p-4 hover:bg-surface-container-low">
                <span>
                    <strong>{{ $question->code }}</strong><span class="ml-3">{{ \Illuminate\Support\Str::limit(strip_tags($question->stem), 100) }}</span>
                    <small class="mt-1 block text-on-surface-variant">Bài học: {{ $question->lessons->pluck('name')->join(', ') ?: '—' }} · Độ khó: {{ $question->difficulty->label() }} · Giảng viên: {{ $question->assignedInstructor?->name ?? '—' }}</small>
                </span>
                <span class="shrink-0 text-primary">Xem →</span>
            </a>
        @empty
            <p class="p-6 text-on-surface-variant">Không có câu hỏi.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $questions->links() }}</div>
</x-layouts.reviewer>
