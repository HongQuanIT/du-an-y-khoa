@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \Modules\Personalization\Models\BookmarkFolder> $folders */
    /** @var \Modules\Personalization\Models\BookmarkFolder|null $activeFolder */
    /** @var \Illuminate\Pagination\LengthAwarePaginator<int, array{id: string, preview: string, stem_html: string, explanation: string, options: list<array{label: string, content: string, correct: bool, explanation: string}>, topic: ?string, difficulty: ?string, saved_at: string, available: bool}> $bookmarks */
    /** @var int $totalCount */
    $currentFolderId = $activeFolder?->id;
@endphp

<x-layouts.app title="{{ $activeFolder ? $activeFolder->name : 'Bộ sưu tập câu hỏi đã lưu' }}">
    <section class="mx-auto max-w-container-max p-4 sm:p-6 md:p-10" x-data="{
        createModalOpen: false,
        newFolderName: '',
        creating: false,
        createError: '',
        selected: [],
        openId: null,
        toggleOpen(id, available) {
            if (!available) return;
            this.openId = this.openId === id ? null : id;
        },
        toggle(id, checked) {
            if (checked) {
                if (!this.selected.includes(id)) this.selected.push(id);
                return;
            }
            this.selected = this.selected.filter((item) => item !== id);
        },
        toggleAll(checked) {
            const boxes = this.$refs.list ? this.$refs.list.querySelectorAll('[data-bookmark-id]') : [];
            this.selected = checked ? Array.from(boxes).map((box) => box.dataset.bookmarkId) : [];
            boxes.forEach((box) => { box.checked = checked; });
        },
        async submitCreateFolder() {
            const name = this.newFolderName.trim();
            if (!name) return;
            this.creating = true;
            this.createError = '';
            try {
                const response = await fetch('/bookmarks/folders', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': @js(csrf_token()),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ name }),
                });
                if (!response.ok) throw new Error('Không thể tạo bộ sưu tập.');
                window.location.reload();
            } catch (err) {
                this.createError = err?.message || 'Không thể tạo bộ sưu tập.';
            } finally {
                this.creating = false;
            }
        }
    }">
        @if (session('status'))
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-primary"
                role="status">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                <p class="font-medium">{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-error/30 bg-error-container px-4 py-3 text-sm text-on-error-container">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($activeFolder === null)
            <!-- VIEW MODE 1: MAIN COLLECTIONS / FOLDERS GRID AT TOP -->
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="mb-2 font-headline-md text-headline-md font-bold text-on-surface">Bộ sưu tập & Câu hỏi đã lưu</h1>
                    <nav class="flex items-center gap-2 text-label-sm text-on-surface-variant">
                        <a class="transition-colors hover:text-primary" href="{{ route('qbank.index') }}">Ngân hàng câu hỏi</a>
                        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                        <span class="font-bold text-primary">Câu hỏi đã lưu</span>
                    </nav>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="createModalOpen = true"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-label-md font-bold text-white shadow-md transition-all hover:bg-primary/90 active:scale-95">
                        <span class="material-symbols-outlined text-[20px]">add</span>
                        Tạo bộ sưu tập mới
                    </button>
                    <a href="{{ route('qbank.create', ['saved_only' => 1]) }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-outline-variant px-5 py-3 text-label-md font-bold text-on-surface transition-colors hover:bg-surface-container-low">
                        Tất cả {{ $totalCount }} câu đã lưu
                    </a>
                </div>
            </div>

            <!-- Folders Grid -->
            @if (! $folders->isEmpty())
                <div class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($folders as $folder)
                        <div class="group relative flex flex-col justify-between rounded-2xl border border-outline-variant bg-white p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md">
                            <div>
                                <div class="mb-4 flex items-center justify-between">
                                    <span class="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                                        <span class="material-symbols-outlined text-[26px]">folder_managed</span>
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-surface-container-high px-3 py-1 text-xs font-bold text-on-surface-variant">
                                            {{ $folder->items_count }} câu hỏi
                                        </span>
                                        <form method="POST" action="{{ route('bookmarks.folders.destroy', $folder) }}"
                                            @submit="if (!confirm('Bạn có chắc chắn muốn xóa bộ sưu tập &quot;{{ $folder->name }}&quot;?')) $event.preventDefault()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="flex size-8 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-error/10 hover:text-error"
                                                title="Xóa bộ sưu tập này">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <h3 class="mb-1 font-title-lg text-title-lg font-bold text-on-surface transition-colors group-hover:text-primary">
                                    <a href="{{ route('qbank.bookmarks', ['folder_id' => $folder->id]) }}">
                                        {{ $folder->name }}
                                    </a>
                                </h3>
                                <p class="text-xs text-on-surface-variant">
                                    Tạo ngày {{ $folder->created_at?->format('d/m/Y') }}
                                </p>
                            </div>

                            <div class="mt-6 flex items-center justify-between border-t border-outline-variant/60 pt-4 text-xs font-bold text-primary">
                                <a href="{{ route('qbank.bookmarks', ['folder_id' => $folder->id]) }}" class="inline-flex items-center gap-1 hover:underline">
                                    <span>Mở bộ sưu tập</span>
                                    <span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-1">arrow_forward</span>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mb-8">
                    {{ $folders->links() }}
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-outline-variant bg-white px-6 py-16 text-center">
                    <span class="material-symbols-outlined mb-3 text-[48px] text-outline">folder_managed</span>
                    <p class="mb-2 font-title-md text-title-md text-on-surface">Chưa có bộ sưu tập nào</p>
                    <p class="mx-auto mb-6 max-w-md text-body-md text-on-surface-variant">
                        Tạo bộ sưu tập mới hoặc lưu câu hỏi khi làm bài để sắp xếp danh sách câu hỏi.
                    </p>
                    <button type="button" @click="createModalOpen = true"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary/90">
                        + Tạo bộ sưu tập mới
                    </button>
                </div>
            @endif

        @else
            <!-- VIEW MODE 2: SPECIFIC COLLECTION DETAIL VIEW -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <a href="{{ route('qbank.bookmarks') }}"
                        class="mb-3 inline-flex items-center gap-1.5 text-xs font-bold text-primary transition-colors hover:text-primary/80">
                        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                        Quay lại tất cả bộ sưu tập
                    </a>
                    <h1 class="mb-1 font-headline-md text-headline-md font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[32px]">folder_managed</span>
                        <span>{{ $activeFolder->name }}</span>
                    </h1>
                    <p class="text-xs font-medium text-on-surface-variant">
                        Danh sách {{ $bookmarks->total() }} câu hỏi trong bộ sưu tập này
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('bookmarks.folders.destroy', $activeFolder) }}"
                        @submit="if (!confirm('Bạn có chắc chắn muốn xóa bộ sưu tập &quot;{{ $activeFolder->name }}&quot;?')) $event.preventDefault()">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-error/30 bg-error/5 px-4 py-3 text-label-md font-bold text-error transition-colors hover:bg-error/10">
                            <span class="material-symbols-outlined text-[18px]">delete</span>
                            Xóa bộ sưu tập này
                        </button>
                    </form>
                    <a href="{{ route('qbank.create') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-label-md font-bold text-white shadow-md transition-all hover:bg-primary/90 active:scale-95">
                        <span class="material-symbols-outlined text-[20px]">add</span>
                        Tạo phiên luyện tập
                    </a>
                </div>
            </div>
        @endif

        <!-- QUESTIONS LIST (rendered when there are questions or when inside a specific active folder) -->
        @if (! $bookmarks->isEmpty())
            <form method="post" action="{{ route('qbank.bookmarks.session') }}" class="space-y-4">
                @csrf
                <div class="flex flex-col gap-3 rounded-2xl border border-outline-variant bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-on-surface">
                        <input type="checkbox" class="size-4 rounded border-outline text-primary focus:ring-primary"
                            @change="toggleAll($event.target.checked)">
                        Chọn tất cả câu trên trang này
                    </label>
                    <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white transition-opacity hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="selected.length === 0">
                        <span class="material-symbols-outlined text-[18px]">play_arrow</span>
                        Tạo phiên từ câu đã chọn
                        <span x-text="selected.length ? '(' + selected.length + ')' : ''"></span>
                    </button>
                </div>

                <div class="overflow-hidden rounded-2xl border border-outline-variant bg-white" x-ref="list">
                    <ul class="divide-y divide-outline-variant">
                        @foreach ($bookmarks as $item)
                            <li class="p-4 md:p-5">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex min-w-0 flex-1 items-start gap-3">
                                        <input type="checkbox" name="question_ids[]" value="{{ $item['id'] }}"
                                            data-bookmark-id="{{ $item['id'] }}"
                                            class="mt-1 size-4 shrink-0 rounded border-outline text-primary focus:ring-primary"
                                            @change="toggle('{{ $item['id'] }}', $event.target.checked)">
                                        <div class="min-w-0 flex-1">
                                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                                @if ($item['topic'])
                                                    <span class="rounded-md bg-surface-container-high px-2.5 py-0.5 text-label-xs font-semibold text-on-surface-variant">
                                                        {{ $item['topic'] }}
                                                    </span>
                                                @endif
                                                @if ($item['difficulty'])
                                                    <span class="rounded-md bg-secondary-container/40 px-2.5 py-0.5 text-label-xs font-semibold text-secondary">
                                                        {{ $item['difficulty'] }}
                                                    </span>
                                                @endif
                                                <span class="text-body-xs text-on-surface-variant/80">Lưu {{ $item['saved_at'] }}</span>
                                            </div>

                                            <p class="font-body-md text-body-md font-semibold leading-relaxed text-on-surface">
                                                {{ $item['preview'] }}
                                            </p>

                                            @if (! $item['available'])
                                                <p class="mt-2 text-label-sm font-semibold text-error">Câu hỏi không còn hiển thị công khai.</p>
                                            @endif

                                            <template x-if="openId === '{{ $item['id'] }}'">
                                                <div class="mt-4 space-y-4 border-t border-outline-variant/60 pt-4">
                                                    <div>
                                                        <p class="mb-2 text-label-sm font-bold text-on-surface-variant">Nội dung câu hỏi</p>
                                                        <div class="prose prose-sm max-w-none text-on-surface" x-html="@js($item['stem_html'])"></div>
                                                    </div>

                                                    @if (count($item['options']) > 0)
                                                        <div>
                                                            <p class="mb-2 text-label-sm font-bold text-on-surface-variant">Các lựa chọn</p>
                                                            <ul class="space-y-2">
                                                                @foreach ($item['options'] as $option)
                                                                    <li @class([
                                                                        'rounded-xl border p-3 text-body-sm',
                                                                        'border-success/40 bg-success/5 font-semibold text-success' => $option['correct'],
                                                                        'border-outline-variant bg-surface-container-lowest text-on-surface-variant' => !$option['correct'],
                                                                    ])>
                                                                        <span class="font-bold mr-2">{{ $option['label'] }}.</span>
                                                                        <span>{!! $option['content'] !!}</span>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    @endif


                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-2 self-end sm:self-start">
                                        <button type="button" @click="toggleOpen('{{ $item['id'] }}', @js($item['available']))"
                                            class="inline-flex items-center gap-1 rounded-xl border border-outline-variant px-3 py-1.5 text-xs font-bold text-on-surface-variant hover:bg-surface-container-low"
                                            :disabled="!@js($item['available'])">
                                            <span x-text="openId === '{{ $item['id'] }}' ? 'Thu gọn' : 'Xem chi tiết'"></span>
                                            <span class="material-symbols-outlined text-[16px]"
                                                x-text="openId === '{{ $item['id'] }}' ? 'expand_less' : 'expand_more'"></span>
                                        </button>

                                        <button type="submit" form="unbookmark-{{ $item['id'] }}"
                                            class="inline-flex items-center justify-center rounded-xl border border-outline-variant p-2 text-on-surface-variant hover:border-error/40 hover:bg-error/10 hover:text-error transition-colors"
                                            title="Xóa câu hỏi">
                                            <span class="material-symbols-outlined text-[18px]">close</span>
                                        </button>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="pt-4">
                    {{ $bookmarks->links() }}
                </div>
            </form>

            @foreach ($bookmarks as $item)
                <form id="unbookmark-{{ $item['id'] }}" method="post" action="{{ route('qbank.bookmarks.destroy', $item['id']) }}?{{ http_build_query(['folder_id' => $currentFolderId]) }}">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        @elseif ($activeFolder !== null)
            <div class="rounded-2xl border border-dashed border-outline-variant bg-white px-6 py-16 text-center">
                <span class="material-symbols-outlined mb-3 text-[40px] text-outline">folder_managed</span>
                <p class="mb-2 font-title-md text-title-md text-on-surface">
                    Bộ sưu tập "{{ $activeFolder->name }}" chưa có câu hỏi nào
                </p>
                <p class="mx-auto mb-6 max-w-md text-body-md text-on-surface-variant">
                    Khi làm bài, nhấn biểu tượng lưu câu hỏi để thêm câu hỏi vào bộ sưu tập của bạn.
                </p>
                <a href="{{ route('qbank.create') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary/90">
                    Bắt đầu luyện tập
                </a>
            </div>
        @endif

        <!-- Create Collection Modal -->
        <div x-show="createModalOpen" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @keydown.escape.window="createModalOpen = false">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm transition-opacity"
                @click="createModalOpen = false"></div>

            <div class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-white p-6 shadow-2xl transition-all"
                @click.stop>
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-headline-sm font-bold text-on-surface">Tạo bộ sưu tập mới</h3>
                    <button type="button" @click="createModalOpen = false"
                        class="flex size-8 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface"
                        aria-label="Đóng">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <form @submit.prevent="submitCreateFolder()" class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-on-surface-variant">Tên bộ sưu tập</label>
                        <input type="text" x-model="newFolderName"
                            placeholder="Ví dụ: khoa, những câu hỏi chính..." required
                            class="w-full rounded-xl border border-outline-variant bg-white px-4 py-2.5 text-sm font-medium text-on-surface placeholder:text-on-surface-variant/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                    </div>

                    <div x-show="createError" x-text="createError" class="text-xs font-semibold text-error"></div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="createModalOpen = false"
                            class="rounded-xl border border-outline-variant px-4 py-2.5 text-sm font-bold text-on-surface-variant hover:bg-surface-container-low">
                            Hủy
                        </button>
                        <button type="submit" :disabled="creating || !newFolderName.trim()"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-primary/90 disabled:opacity-50">
                            <span x-show="creating" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                            <span>Tạo bộ sưu tập</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</x-layouts.app>
