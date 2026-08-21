@php
    // Teach classroom detail stub (Phase B).
@endphp

<x-layouts.teach :title="$classroom->title">
    <div class="mb-6">
        <a href="{{ route('teach.classes.index') }}"
            class="mb-4 inline-flex items-center gap-1 rounded-lg px-2 py-1 font-label-sm text-label-sm text-primary no-underline hover:bg-primary/5 hover:no-underline">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Lớp của tôi
        </a>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 font-body-sm text-body-sm text-primary">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-primary/10 px-2.5 py-0.5 font-label-sm text-primary">
                        {{ $classroom->purpose->label() }}
                    </span>
                    <span class="rounded-full bg-surface-container-high px-2.5 py-0.5 font-label-sm text-on-surface-variant">
                        {{ $classroom->visibility->label() }}
                    </span>
                    @if ($classroom->status === \Modules\Classroom\Enums\ClassroomStatus::PendingApproval)
                        <span class="rounded-full bg-tertiary/15 px-2.5 py-0.5 font-label-sm font-semibold text-tertiary">Chờ duyệt</span>
                    @endif
                    @if ($classroom->liveSession)
                        <span class="rounded-full bg-error/10 px-2.5 py-0.5 font-label-sm font-semibold text-error">LIVE</span>
                    @endif
                </div>
                <h2 class="font-headline-sm text-headline-sm text-on-surface">{{ $classroom->title }}</h2>
                <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                    Host: <span class="text-on-surface">{{ $classroom->host?->name }}</span>
                    · {{ $classroom->active_members_count }} thành viên
                    · {{ $classroom->status->label() }}
                </p>
                @if ($classroom->description)
                    <p class="mt-3 max-w-2xl font-body-md text-body-md text-on-surface">{{ $classroom->description }}</p>
                @endif
                @if ($classroom->join_code)
                    <p class="mt-3 inline-flex items-center gap-2 rounded-lg bg-surface-container-low px-3 py-1.5 font-body-sm text-body-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-[18px]">vpn_key</span>
                        Mã tham gia: <strong class="text-on-surface">{{ $classroom->join_code }}</strong>
                    </p>
                @endif
            </div>
            <form method="post" action="{{ route('teach.classes.destroy', $classroom) }}"
                onsubmit="return confirm('Xoá lớp này? Hành động này sẽ ẩn lớp khỏi portal giảng viên và học viên.')"
                class="shrink-0">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg border border-error px-3 py-2 font-label-sm font-semibold text-error hover:bg-error/5">
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                    Xoá lớp
                </button>
            </form>
        </div>
    </div>

    @if ($classroom->status === \Modules\Classroom\Enums\ClassroomStatus::PendingApproval)
        <div class="mb-6 rounded-xl border border-tertiary/30 bg-tertiary/10 px-4 py-3 font-body-sm text-body-sm text-on-surface">
            Lớp đang <strong>chờ admin duyệt</strong>. Bạn có thể mở Studio để kiểm tra camera và nội dung; học viên chỉ thấy lớp sau khi được duyệt.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <div class="mb-4">
                    <div>
                        <h3 class="font-title-md text-title-md text-on-surface">Buổi live</h3>
                        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Tạo lịch, chọn câu hỏi và quản lý buổi phát của lớp.</p>
                    </div>
                </div>

                @if (! $classroom->liveSession)
                    <div x-data="classroomQuestionPicker(@js(route('teach.classes.questions.search', $classroom)))" x-init="loadQuestions()"
                        class="mb-5 border-y border-outline-variant py-5">
                        <form method="post" action="{{ route('teach.classes.sessions.store', $classroom) }}" class="space-y-5">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px]">
                                <label>
                                    <span class="mb-1 block font-label-sm text-on-surface-variant">Tiêu đề buổi live</span>
                                    <input name="title" value="{{ old('title') }}" required maxlength="200"
                                        placeholder="Ví dụ: Chữa đề Nội khoa"
                                        class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 font-body-sm text-on-surface">
                                </label>
                                <label>
                                    <span class="mb-1 block font-label-sm text-on-surface-variant">Thời gian</span>
                                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"
                                        class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 font-body-sm text-on-surface">
                                </label>
                            </div>

                            <div class="grid min-h-[320px] gap-4 lg:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <h4 class="font-label-md text-on-surface">Câu đã chọn</h4>
                                        <span class="font-label-sm text-on-surface-variant" x-text="selected.length + '/50 câu'">0/50 câu</span>
                                    </div>
                                    <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
                                        <template x-for="(question, index) in selected" :key="question.id">
                                            <div class="flex items-start gap-3 rounded-lg bg-surface-container-lowest p-3">
                                                <input type="hidden" name="question_ids[]" :value="question.id">
                                                <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/10 font-label-sm text-primary" x-text="index + 1"></span>
                                                <div class="min-w-0 flex-1">
                                                    <p class="line-clamp-2 font-body-sm text-on-surface" x-text="question.stem"></p>
                                                    <p class="mt-1 font-label-sm text-on-surface-variant" x-text="question.topic + ' · ' + question.difficulty"></p>
                                                </div>
                                                <button type="button" @click="remove(question.id)" class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-error hover:bg-error/10" aria-label="Bỏ câu hỏi">
                                                    <span class="material-symbols-outlined text-[19px]">close</span>
                                                </button>
                                            </div>
                                        </template>
                                        <div x-show="selected.length === 0" class="rounded-lg border border-dashed border-outline-variant px-4 py-10 text-center">
                                            <span class="material-symbols-outlined text-3xl text-on-surface-variant">playlist_add</span>
                                            <p class="mt-2 font-body-sm text-on-surface-variant">Chưa chọn câu hỏi. Buổi live vẫn có thể tạo không kèm đề.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="min-w-0 border-t border-outline-variant pt-4 lg:border-l lg:border-t-0 lg:pl-4 lg:pt-0">
                                    <label class="relative block">
                                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-on-surface-variant">search</span>
                                        <input type="search" x-model="search" @input="scheduleSearch()" placeholder="Tìm nội dung câu hỏi..."
                                            class="w-full rounded-lg border border-outline-variant bg-surface py-2 pl-10 pr-3 font-body-sm text-on-surface">
                                    </label>
                                    <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
                                        <template x-for="question in availableQuestions" :key="question.id">
                                            <button type="button" @click="add(question)" :disabled="selected.some(item => item.id === question.id) || selected.length >= 50"
                                                class="block w-full rounded-lg border border-outline-variant bg-surface p-3 text-left hover:border-primary hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-45">
                                                <p class="line-clamp-2 font-body-sm text-on-surface" x-text="question.stem"></p>
                                                <p class="mt-1 font-label-sm text-on-surface-variant" x-text="question.topic + ' · ' + question.difficulty"></p>
                                            </button>
                                        </template>
                                        <p x-show="loading" class="py-8 text-center font-body-sm text-on-surface-variant">Đang tìm câu hỏi...</p>
                                        <p x-show="!loading && availableQuestions.length === 0" class="py-8 text-center font-body-sm text-on-surface-variant">Không tìm thấy câu hỏi phù hợp.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary hover:opacity-90">
                                    <span class="material-symbols-outlined text-[20px]">event</span>
                                    Lên lịch buổi live
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-error/20 bg-error/5 px-3 py-2 font-body-sm text-error">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if ($classroom->liveSession)
                    <div class="mb-4 rounded-lg border border-error/20 bg-error/5 px-4 py-3 font-body-sm text-body-sm text-error">
                        Đang có buổi live: {{ $classroom->liveSession->title }}
                        <a href="{{ route('teach.classes.sessions.studio', [$classroom, $classroom->liveSession]) }}"
                            class="mt-3 inline-flex items-center gap-2 rounded-lg bg-error px-3 py-1.5 font-label-sm font-semibold text-on-error hover:opacity-90">
                            <span class="material-symbols-outlined text-[18px]">videocam</span>
                            Vào Live Studio
                        </a>
                        <form method="post" action="{{ route('teach.classes.sessions.end', [$classroom, $classroom->liveSession]) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="rounded-lg border border-error px-3 py-1.5 font-label-sm font-semibold text-error hover:bg-error/5">Kết thúc live</button>
                        </form>
                    </div>
                @endif

                @if ($upcomingSessions->isNotEmpty())
                    <ul class="mb-4 space-y-2">
                        @foreach ($upcomingSessions as $session)
                            <li class="flex items-center justify-between rounded-lg bg-surface-container-lowest px-3 py-2.5">
                                <div>
                                    <p class="font-label-md text-on-surface">{{ $session->title }}</p>
                                    <p class="font-body-sm text-on-surface-variant">
                                        {{ $session->scheduled_at?->format('d/m/Y H:i') ?? 'Chưa đặt giờ' }}
                                        · {{ $session->status->label() }}
                                        @if ($session->hasQuestionSet())
                                            · {{ count($session->questionIds()) }} câu hỏi
                                        @endif
                                    </p>
                                </div>
                                @if ($session->status === \Modules\Classroom\Enums\LiveSessionStatus::Scheduled)
                                    <form method="post" action="{{ route('teach.classes.sessions.start', [$classroom, $session]) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-primary px-3 py-1.5 font-label-sm font-semibold text-on-primary hover:opacity-90">Bắt đầu</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="rounded-lg border border-dashed border-outline-variant bg-surface-container-lowest px-4 py-8 text-center">
                        <span class="material-symbols-outlined text-[32px] text-on-surface-variant">event</span>
                        <p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">Chưa có lịch live.</p>
                    </div>
                @endif

                @if ($pastSessions->isNotEmpty())
                    <div class="mt-6 border-t border-outline-variant pt-4">
                        <p class="mb-2 font-label-sm text-on-surface-variant uppercase tracking-wide">Buổi trước</p>
                        <ul class="space-y-2">
                            @foreach ($pastSessions as $session)
                                <li class="font-body-sm text-on-surface-variant">
                                    {{ $session->title }}
                                    · {{ $session->status->label() }}
                                    @if ($session->ended_at)
                                        · {{ $session->ended_at->format('d/m/Y') }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <h3 class="font-title-md text-title-md text-on-surface">Thành viên</h3>
                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                    {{ $classroom->active_members_count }} đang tham gia
                    @if ($classroom->max_members)
                        / tối đa {{ $classroom->max_members }}
                    @endif
                </p>
                <ul class="mt-4 max-h-64 space-y-2 overflow-y-auto">
                    @forelse ($members as $member)
                        <li class="flex items-center justify-between gap-2 font-body-sm">
                            <span class="truncate text-on-surface">{{ $member->user?->name ?? '—' }}</span>
                            <span class="shrink-0 font-label-sm text-on-surface-variant">{{ $member->role_in_class->label() }}</span>
                        </li>
                    @empty
                        <li class="font-body-sm text-on-surface-variant">Chưa có thành viên.</li>
                    @endforelse
                </ul>
                @if ($classroom->active_members_count > $members->count())
                    <p class="mt-3 font-body-sm text-on-surface-variant">Đang hiển thị 50 thành viên mới nhất.</p>
                @endif
            </section>

            <section class="rounded-xl border border-outline-variant bg-surface p-5">
                <h3 class="font-title-md text-title-md text-on-surface">Bước tiếp theo</h3>
                <ul class="mt-3 space-y-2 font-body-sm text-body-sm text-on-surface-variant">
                    <li>• Chọn tối đa 50 câu hỏi cho buổi live</li>
                    <li>• Lên lịch và kiểm tra lại bộ câu hỏi</li>
                    <li>• Mở Live Studio để bắt đầu chữa đề</li>
                </ul>
            </section>
        </aside>
    </div>

    <script>
        window.classroomQuestionPicker = (searchUrl) => ({
            availableQuestions: [],
            selected: [],
            search: '',
            loading: false,
            searchTimer: null,
            requestSequence: 0,

            scheduleSearch() {
                window.clearTimeout(this.searchTimer);
                this.searchTimer = window.setTimeout(() => this.loadQuestions(), 350);
            },

            async loadQuestions() {
                const sequence = ++this.requestSequence;
                this.loading = true;

                try {
                    const url = new URL(searchUrl, window.location.origin);
                    if (this.search.trim()) {
                        url.searchParams.set('q', this.search.trim());
                    }
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        throw new Error('Không tải được thư viện câu hỏi.');
                    }

                    // Bỏ kết quả cũ nếu người dùng đã nhập một từ khóa mới hơn.
                    if (sequence === this.requestSequence) {
                        const payload = await response.json();
                        this.availableQuestions = payload.data?.questions ?? [];
                    }
                } catch (error) {
                    if (sequence === this.requestSequence) {
                        this.availableQuestions = [];
                    }
                    console.error('[Classroom] question search', error);
                } finally {
                    if (sequence === this.requestSequence) {
                        this.loading = false;
                    }
                }
            },

            add(question) {
                if (this.selected.length >= 50 || this.selected.some(item => item.id === question.id)) {
                    return;
                }
                this.selected.push(question);
            },

            remove(questionId) {
                this.selected = this.selected.filter(question => question.id !== questionId);
            },
        });
    </script>
</x-layouts.teach>
