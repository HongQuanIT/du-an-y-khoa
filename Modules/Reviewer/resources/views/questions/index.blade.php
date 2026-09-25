<x-layouts.reviewer title="Review câu hỏi" :pending-count="$stats['pending']">
    <x-admin.page-header title="Review câu hỏi"
        description="Câu hỏi đã được giảng viên duyệt chuyên môn. Đọc nội dung rồi gắn cờ xanh, vàng hoặc đỏ.">
    </x-admin.page-header>

    <x-admin.flash />

    <nav class="mb-5 flex gap-2" aria-label="Trạng thái hàng đợi review">
        <a href="{{ route('reviewer.questions.flags.index', ['tab' => 'pending']) }}"
            @class([
                'rounded-lg px-3 py-2 font-label-sm',
                'bg-primary text-on-primary' => ($tab ?? 'pending') === 'pending',
                'bg-surface-container-low text-on-surface-variant' => ($tab ?? 'pending') !== 'pending',
            ])>Chờ gắn cờ ({{ $stats['pending'] }})</a>
        <a href="{{ route('reviewer.questions.flags.index', ['tab' => 'done']) }}"
            @class([
                'rounded-lg px-3 py-2 font-label-sm',
                'bg-primary text-on-primary' => ($tab ?? '') === 'done',
                'bg-surface-container-low text-on-surface-variant' => ($tab ?? '') !== 'done',
            ])>Đã gắn cờ ({{ $stats['done'] }})</a>
    </nav>

    <div class="overflow-hidden rounded-2xl border border-outline-variant bg-surface">
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-container-low text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3 font-label-sm">Mã</th>
                    <th class="px-4 py-3 font-label-sm">Câu hỏi</th>
                    <th class="px-4 py-3 font-label-sm">Bài học</th>
                    <th class="px-4 py-3 font-label-sm">Độ khó</th>
                    <th class="px-4 py-3 font-label-sm">Giảng viên</th>
                    <th class="px-4 py-3 font-label-sm"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($questions as $question)
                    <tr class="border-t border-outline-variant">
                        <td class="px-4 py-3 font-semibold">{{ $question->code }}</td>
                        <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit(strip_tags($question->stem), 80) }}</td>
                        <td class="px-4 py-3">
                            @if ($question->lessons->isNotEmpty())
                                {{ $question->lessons->pluck('name')->join(', ') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $question->difficulty->label() }}</td>
                        <td class="px-4 py-3">{{ $question->assignedInstructor?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('reviewer.questions.flags.show', $question) }}" class="font-semibold text-primary">Xem</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-on-surface-variant">Không có câu hỏi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $questions->links() }}</div>
</x-layouts.reviewer>
