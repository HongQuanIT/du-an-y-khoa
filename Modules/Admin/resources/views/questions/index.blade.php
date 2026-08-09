<x-layouts.admin title="Câu hỏi">
    <x-admin.page-header title="Ngân hàng câu hỏi"
        description="Soạn, duyệt và xuất bản câu hỏi (Phase 2a).">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('admin.questions.create') }}"
                    class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary hover:opacity-90">Tạo câu hỏi</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <form method="get" action="{{ route('admin.questions.index') }}"
        class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-outline-variant bg-surface p-4 sm:grid-cols-4">
        <div class="sm:col-span-2">
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="q">Tìm câu hỏi</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" type="search"
                class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="status">Trạng thái</label>
            <select id="status" name="status" class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
                <option value="">Tất cả</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="difficulty">Độ khó</label>
            <select id="difficulty" name="difficulty" class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
                <option value="">Tất cả</option>
                @foreach ($difficulties as $difficulty)
                    <option value="{{ $difficulty->value }}" @selected($filters['difficulty'] === $difficulty->value)>{{ $difficulty->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1 block font-label-sm text-on-surface-variant" for="topic_id">Chủ đề</label>
            <select id="topic_id" name="topic_id" class="w-full rounded-lg border-none bg-surface-container-low px-3 py-2 font-body-sm">
                <option value="">Tất cả</option>
                @foreach ($topics as $topic)
                    <option value="{{ $topic->id }}" @selected((string) $filters['topic_id'] === (string) $topic->id)>{{ $topic->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2 flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 font-label-md text-on-primary">Lọc</button>
            <a href="{{ route('admin.questions.index') }}" class="rounded-lg px-4 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">Xóa lọc</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-outline-variant bg-surface">
        <table class="min-w-full text-left font-body-sm text-body-sm">
            <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Câu hỏi</th>
                    <th class="px-4 py-3">Chủ đề</th>
                    <th class="px-4 py-3">Độ khó</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3">Free</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($questions as $question)
                    <tr class="border-b border-outline-variant/60 last:border-0">
                        <td class="px-4 py-3 max-w-md">
                            <div class="line-clamp-2 text-on-surface">{{ \Illuminate\Support\Str::limit(strip_tags($question->stem), 140) }}</div>
                            <div class="font-label-sm text-on-surface-variant">v{{ $question->version }} · {{ $question->updated_at?->diffForHumans() }}</div>
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $question->topic?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $question->difficulty->label() }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $question->status->label() }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $question->is_free ? 'Có' : 'Không' }}</td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('admin.questions.edit', $question) }}" class="font-label-md text-primary hover:underline">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-on-surface-variant">Chưa có câu hỏi khớp bộ lọc.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $questions->links() }}</div>
</x-layouts.admin>
