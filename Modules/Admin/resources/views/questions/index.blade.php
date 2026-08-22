<x-layouts.admin :title="$isReviewer ? 'Ngân hàng câu hỏi' : 'Câu hỏi của tôi'">
    <x-admin.page-header :title="$isReviewer ? 'Ngân hàng câu hỏi' : 'Câu hỏi của tôi'"
        :description="$isReviewer ? 'Quản lý, kiểm duyệt và xuất bản câu hỏi trong hệ thống.' : 'Bạn chỉ nhìn thấy các câu hỏi do chính mình tạo.'">
        <x-slot:actions>
            @if ($canCreate)
                <a href="{{ route('admin.questions.create') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary shadow-sm transition-all hover:bg-primary/90 hover:shadow">
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Tạo câu hỏi mới
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    <!-- Summary Stats Bar -->
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <span class="material-symbols-outlined text-[22px]">help_center</span>
                </div>
                <div>
                    <p class="text-label-sm font-medium text-on-surface-variant">Tổng số câu hỏi</p>
                    <p class="text-headline-sm font-bold text-on-surface">{{ number_format($stats['total']) }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-success/10 text-success">
                    <span class="material-symbols-outlined text-[22px]">check_circle</span>
                </div>
                <div>
                    <p class="text-label-sm font-medium text-on-surface-variant">Đã xuất bản</p>
                    <p class="text-headline-sm font-bold text-on-surface">
                        {{ number_format($stats['published']) }}
                    </p>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-warning/10 text-warning">
                    <span class="material-symbols-outlined text-[22px]">hourglass_empty</span>
                </div>
                <div>
                    <p class="text-label-sm font-medium text-on-surface-variant">Đang chờ duyệt</p>
                    <p class="text-headline-sm font-bold text-on-surface">
                        {{ number_format($stats['pending']) }}
                    </p>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-outline-variant bg-surface p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-secondary/10 text-secondary">
                    <span class="material-symbols-outlined text-[22px]">stars</span>
                </div>
                <div>
                    <p class="text-label-sm font-medium text-on-surface-variant">Miễn phí</p>
                    <p class="text-headline-sm font-bold text-on-surface">
                        {{ number_format($stats['free']) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="mb-6 rounded-2xl border border-outline-variant bg-surface p-5 shadow-sm">
        <form method="get" action="{{ route('admin.questions.index') }}" class="grid grid-cols-1 items-end gap-4 sm:grid-cols-12">
            <div class="sm:col-span-3">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="q">Tìm kiếm từ khóa</label>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
                    <input id="q" name="q" value="{{ $filters['q'] }}" type="search"
                        placeholder="Nhập nội dung câu hỏi..."
                        class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest pr-3 pl-9 font-body-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                </div>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="topic_id">Chủ đề</label>
                <select id="topic_id" name="topic_id" class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 font-body-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                    <option value="">Tất cả chủ đề</option>
                    @foreach ($topics as $topic)
                        <option value="{{ $topic->id }}" @selected((string) $filters['topic_id'] === (string) $topic->id)>{{ $topic->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="status">Trạng thái</label>
                <select id="status" name="status" class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 font-body-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                    <option value="">Tất cả trạng thái</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="review">Kiểm duyệt</label>
                <select id="review" name="review" class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 font-body-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                    <option value="">Tất cả</option>
                    <option value="pending" @selected($filters['review'] === 'pending')>Đang chờ duyệt</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block font-label-sm font-semibold text-on-surface-variant" for="difficulty">Độ khó</label>
                <select id="difficulty" name="difficulty" class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 font-body-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary">
                    <option value="">Tất cả độ khó</option>
                    @foreach ($difficulties as $difficulty)
                        <option value="{{ $difficulty->value }}" @selected($filters['difficulty'] === $difficulty->value)>{{ $difficulty->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-1 flex gap-2">
                <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-primary font-label-md font-semibold text-on-primary hover:bg-primary/90">
                    Lọc
                </button>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-[1100px] w-full text-left font-body-sm text-on-surface">
                <thead class="border-b border-outline-variant bg-surface-container-low font-label-md text-on-surface-variant">
                    <tr>
                        <th class="px-5 py-3.5">Nội dung câu hỏi</th>
                        <th class="px-4 py-3.5">Chủ đề</th>
                        <th class="px-4 py-3.5">Độ khó</th>
                        @if ($isReviewer)<th class="px-4 py-3.5">Người tạo</th>@endif
                        <th class="px-4 py-3.5">Kiểm duyệt</th>
                        <th class="px-4 py-3.5">Trạng thái</th>
                        <th class="px-4 py-3.5">Loại quyền</th>
                        <th class="px-5 py-3.5 text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse ($questions as $question)
                        <tr class="transition-colors hover:bg-surface-container-lowest">
                            <td class="px-5 py-4 max-w-lg">
                                <a href="{{ route('admin.questions.edit', $question) }}" class="group block">
                                    <p class="line-clamp-2 font-medium text-on-surface group-hover:text-primary transition-colors">
                                        {{ \Illuminate\Support\Str::limit(strip_tags($question->stem), 140) }}
                                    </p>
                                    <p class="mt-1 font-label-sm text-on-surface-variant">
                                        Phiên bản {{ $question->version }} · Cập nhật {{ $question->updated_at?->diffForHumans() }}
                                    </p>
                                </a>
                            </td>
                            <td class="px-4 py-4">
                                @if($question->topics->isNotEmpty())
                                    <div class="flex max-w-64 flex-wrap gap-1">
                                        @foreach ($question->topics->take(2) as $topic)
                                            <span class="inline-flex items-center whitespace-nowrap rounded-lg bg-surface-container-high px-2.5 py-1 text-xs font-semibold text-on-surface">
                                                {{ $topic->name }}
                                            </span>
                                        @endforeach
                                        @if ($question->topics->count() > 2)
                                            <span class="inline-flex items-center rounded-lg bg-primary/10 px-2 py-1 text-xs font-semibold text-primary">
                                                +{{ $question->topics->count() - 2 }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-on-surface-variant/60">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="text-xs font-semibold text-on-surface-variant">
                                    {{ $question->difficulty->label() }}
                                </span>
                            </td>
                            @if ($isReviewer)
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="font-medium">{{ $question->creator?->name ?? 'Dữ liệu hệ thống' }}</span>
                                </td>
                            @endif
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if ($question->pendingReviewRequest)
                                    @if ($isReviewer)
                                        <a href="{{ route('admin.questions.reviews.show', $question->pendingReviewRequest) }}"
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800 hover:bg-amber-200">
                                            {{ $question->pendingReviewRequest->action->label() }} · Chờ duyệt
                                        </a>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">
                                            {{ $question->pendingReviewRequest->action->label() }} · Chờ duyệt
                                        </span>
                                    @endif
                                @else
                                    <span class="text-on-surface-variant/60">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @php
                                    $badgeClass = match($question->status) {
                                        \Modules\QuestionBank\Enums\QuestionStatus::Published => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
                                        \Modules\QuestionBank\Enums\QuestionStatus::InReview => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
                                        \Modules\QuestionBank\Enums\QuestionStatus::Draft => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                        \Modules\QuestionBank\Enums\QuestionStatus::Retired => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold {{ $badgeClass }}">
                                    {{ $question->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if($question->is_free)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-bold text-primary">
                                        Miễn phí
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-secondary/10 px-2.5 py-0.5 text-xs font-bold text-secondary">
                                        Trả phí (Premium)
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-end">
                                <a href="{{ route('admin.questions.edit', $question) }}"
                                    class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">
                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                    Chỉnh sửa
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isReviewer ? 8 : 7 }}" class="px-5 py-12 text-center">
                                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-surface-container-high text-on-surface-variant">
                                    <span class="material-symbols-outlined text-[28px]">search_off</span>
                                </div>
                                <p class="mt-3 font-label-lg font-semibold text-on-surface">Không tìm thấy câu hỏi nào</p>
                                <p class="text-body-sm text-on-surface-variant">Thử thay đổi bộ lọc tìm kiếm hoặc tạo câu hỏi mới.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $questions->links() }}</div>
</x-layouts.admin>
