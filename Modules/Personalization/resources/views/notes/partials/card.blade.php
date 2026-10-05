{{-- Note card — style bản đầu, meta môn/bài tùy chế độ nhóm --}}
<article
    class="rounded-xl border border-outline-variant bg-white p-5 transition-shadow hover:shadow-sm"
    @if ($note['color'] ?? null) style="border-left: 4px solid {{ $note['color'] }}" @endif>
    <div class="mb-2 flex flex-wrap items-center gap-2">
        <span class="rounded-full bg-surface-container px-2.5 py-0.5 font-label-sm text-label-sm text-on-surface-variant">
            {{ $note['type_label'] }}
        </span>
        @if ($note['orphan'] ?? false)
            <span class="rounded-full bg-amber-50 px-2.5 py-0.5 font-label-sm text-label-sm text-amber-700">
                Nội dung đã gỡ
            </span>
        @endif
        <span class="font-label-sm text-label-sm text-on-surface-variant">
            {{ $note['updated_at_label'] }}
        </span>
    </div>

    @if (! empty($note['source_title']))
        <p class="mb-2 font-label-md text-label-md text-on-surface">
            {{ $note['source_title'] }}
        </p>
    @endif

    @php
        $metaParts = [];
        if (($groupBy ?? 'subject') === 'subject') {
            // Đang nhóm theo môn → hiện bài học phụ
            foreach ($note['lessons'] ?? [] as $lesson) {
                $metaParts[] = $lesson['name'];
            }
        } elseif (($groupBy ?? '') === 'lesson') {
            // Đang nhóm theo bài → hiện môn học phụ
            foreach ($note['subjects'] ?? [] as $subject) {
                $metaParts[] = $subject['name'];
            }
        } else {
            foreach ($note['subjects'] ?? [] as $subject) {
                $metaParts[] = $subject['name'];
            }
            foreach ($note['lessons'] ?? [] as $lesson) {
                $metaParts[] = $lesson['name'];
            }
        }
        $metaParts = array_values(array_unique($metaParts));
    @endphp
    @if ($metaParts !== [])
        <p class="mb-2 font-label-sm text-label-sm text-on-surface-variant">
            {{ implode(' · ', $metaParts) }}
        </p>
    @endif

    <div class="prose prose-sm max-w-none text-body-md text-on-surface">
        {!! $note['body_html'] ?: e($note['body']) !!}
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <button type="button"
            @click="openEdit(@js($note))"
            class="inline-flex items-center gap-1 rounded-lg border border-outline-variant px-3 py-1.5 font-label-sm text-on-surface-variant hover:bg-surface-container-low">
            <span class="material-symbols-outlined text-[16px]">edit</span>
            Sửa
        </button>
        <button type="button"
            @click="removeNote({{ $note['id'] }})"
            class="inline-flex items-center gap-1 rounded-lg border border-error/30 px-3 py-1.5 font-label-sm text-error hover:bg-error/5">
            <span class="material-symbols-outlined text-[16px]">delete</span>
            Xóa
        </button>
    </div>
</article>
