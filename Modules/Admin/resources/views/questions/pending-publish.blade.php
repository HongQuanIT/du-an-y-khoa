<x-layouts.admin title="Chờ xuất bản">
    <x-admin.page-header title="Chờ xuất bản"
        description="Câu đã được giảng viên duyệt và đủ 2 cờ reviewer. Cờ đỏ không cho xuất bản.">
    </x-admin.page-header>

    <x-admin.flash />

    <div class="overflow-hidden rounded-2xl border border-outline-variant bg-surface">
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-container-low text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3 font-label-sm">Mã</th>
                    <th class="px-4 py-3 font-label-sm">Nội dung</th>
                    <th class="px-4 py-3 font-label-sm">Giảng viên</th>
                    <th class="px-4 py-3 font-label-sm">Cờ</th>
                    <th class="px-4 py-3 font-label-sm"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($questions as $question)
                    <tr class="border-t border-outline-variant">
                        <td class="px-4 py-3 font-semibold">{{ $question->code }}</td>
                        <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit(strip_tags($question->stem), 80) }}</td>
                        <td class="px-4 py-3">{{ $question->assignedInstructor?->name ?? $question->instructor?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @include('questionbank::partials.instructor-review-flags', ['question' => $question])
                            @if ($question->hasRedReviewerFlag())
                                <span class="ml-1 text-[11px] font-semibold text-rose-700">Chặn xuất bản</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($question->published_version)
                                <a href="{{ route('admin.questions.compare', $question) }}" class="mr-3 font-semibold text-on-surface-variant hover:text-on-surface">So sánh</a>
                            @endif
                            <a href="{{ route('admin.questions.edit', $question) }}" class="font-semibold text-primary">Duyệt xuất bản</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">Không có câu chờ xuất bản.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $questions->links() }}</div>
</x-layouts.admin>
