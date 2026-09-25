<x-layouts.reviewer title="Review câu hỏi" :pending-count="$stats['pending']">
    <x-admin.page-header title="Review câu hỏi"
        description="Câu hỏi đã được giảng viên duyệt chuyên môn. Đọc nội dung rồi chọn Đạt hoặc Không đạt (độc lập — không thấy quyết định của reviewer khác).">
    </x-admin.page-header>

    <x-admin.flash />

    <nav class="mb-5 flex flex-wrap gap-2" aria-label="Trạng thái hàng đợi review">
        <a href="{{ route('reviewer.questions.flags.index', ['tab' => 'pending']) }}"
            @class([
                'rounded-lg px-3 py-2 font-label-sm',
                'bg-primary text-on-primary' => ($tab ?? 'pending') === 'pending',
                'bg-surface-container-low text-on-surface-variant' => ($tab ?? 'pending') !== 'pending',
            ])>Chờ gắn cờ ({{ $stats['pending'] }})</a>
        <a href="{{ route('reviewer.questions.flags.index', ['tab' => 'warning']) }}"
            @class([
                'rounded-lg px-3 py-2 font-label-sm',
                'bg-amber-600 text-white' => ($tab ?? '') === 'warning',
                'bg-amber-50 text-amber-900 border border-amber-200' => ($tab ?? '') !== 'warning',
            ])>Cảnh báo ({{ $stats['warning'] }})</a>
        <a href="{{ route('reviewer.questions.flags.index', ['tab' => 'done']) }}"
            @class([
                'rounded-lg px-3 py-2 font-label-sm',
                'bg-primary text-on-primary' => ($tab ?? '') === 'done',
                'bg-surface-container-low text-on-surface-variant' => ($tab ?? '') !== 'done',
            ])>Đã gắn cờ ({{ $stats['done'] }})</a>
    </nav>

    @if (($tab ?? '') === 'warning')
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            Kết quả gắn cờ chưa thống nhất. Hãy đọc lại toàn bộ câu hỏi. Bạn chỉ thấy quyết định của mình —
            đổi cờ chỉ khi đã kiểm tra kỹ và chấp nhận trách nhiệm.
        </div>
    @endif

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
                    @php
                        $returnedToEditorLabel = null;
                        if (($tab ?? '') === 'done'
                            && $question->hasStickyReviewers()
                            && (
                                (int) $question->sticky_reviewer_1_id === (int) auth()->id()
                                || (int) $question->sticky_reviewer_2_id === (int) auth()->id()
                            )
                        ) {
                            if ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Rejected
                                && $question->isDualRedRejection()) {
                                $returnedToEditorLabel = 'Đã trả Editor';
                            } elseif ($question->status === \Modules\QuestionBank\Enums\QuestionStatus::Draft
                                && $question->isStickyResubmitEligible()) {
                                $returnedToEditorLabel = 'Đã trả Editor · chờ sửa';
                            }
                        }
                    @endphp
                    <tr class="border-t border-outline-variant">
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ $question->code }}</span>
                                @if ($returnedToEditorLabel)
                                    <span class="inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-800"
                                        data-testid="returned-to-editor-badge">{{ $returnedToEditorLabel }}</span>
                                @endif
                            </div>
                        </td>
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
