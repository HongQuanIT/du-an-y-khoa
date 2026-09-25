<x-layouts.reviewer title="Review câu hỏi — {{ $question->code }}" :pending-count="0">
    <x-admin.page-header :title="'Review câu hỏi '.$question->code"
        description="Xem câu như học viên rồi chọn Đạt hoặc Không đạt (Không đạt bắt buộc ghi chú).">
        <x-slot:actions>
            <a href="{{ route('reviewer.questions.flags.index', ['tab' => $inConflict ? 'warning' : 'pending']) }}"
                class="rounded-lg px-3 py-2 font-label-md text-on-surface-variant hover:bg-surface-container-low">← Hàng đợi</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.flash />

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-error/30 bg-error/5 px-4 py-3 text-sm text-error">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($inConflict)
        <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">Cần rà soát lại</p>
            <p class="mt-1">Kết quả gắn cờ chưa thống nhất. Hãy đọc lại toàn bộ câu hỏi rồi xác nhận quyết định của bạn.
                Giữ nguyên nếu vẫn đúng; chỉ đổi khi đã kiểm tra kỹ và chịu trách nhiệm với quyết định của mình.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        @include('questionbank::partials.question-learner-preview', [
            'question' => $question,
            'revealAnswers' => true,
        ])

        <div class="xl:sticky xl:top-4">
            @if ($canFlag)
                <form method="post" action="{{ route('reviewer.questions.flags.store', $question) }}"
                    class="rounded-2xl border border-outline-variant bg-surface p-5"
                    x-data="{ flag: '{{ old('flag', '') }}' }">
                    @csrf
                    <p class="mb-3 text-sm text-on-surface-variant">
                        Chọn kết quả. Hệ thống ghi nhận độc lập; chỉ chốt khi các quyết định đã thống nhất.
                    </p>
                    @include('admin::questions.flags.partials.flag-chooser', [
                        'flags' => $flags,
                        'checkedValue' => null,
                    ])
                    <label class="mb-1 block text-xs font-semibold text-on-surface-variant" for="note">
                        <span x-text="flag === 'red' ? 'Ghi chú (bắt buộc khi Không đạt)' : 'Ghi chú (tuỳ chọn)'"></span>
                    </label>
                    <textarea id="note" name="note" rows="3"
                        :required="flag === 'red'"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm">{{ old('note') }}</textarea>
                    <button type="submit" class="mt-4 w-full rounded-xl bg-primary px-4 py-2.5 font-label-md font-semibold text-on-primary">
                        Gửi quyết định
                    </button>
                </form>
            @elseif ($canChangeInConflict)
                <div class="rounded-2xl border border-amber-200 bg-surface p-5"
                    x-data="{
                        flag: '{{ old('flag', $ownFlag?->value ?? '') }}',
                        original: '{{ $ownFlag?->value ?? '' }}',
                        showAck: false,
                        acked: false,
                        get isChange() { return this.flag !== '' && this.flag !== this.original },
                        openAck() {
                            if (! this.isChange) return;
                            this.acked = false;
                            this.showAck = true;
                        },
                        cancelAck() {
                            this.showAck = false;
                            this.acked = false;
                        },
                        confirmChange() {
                            if (! this.acked) return;
                            this.showAck = false;
                            this.$refs.conflictForm.requestSubmit();
                        }
                    }"
                    @keydown.escape.window="if (showAck) cancelAck()">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="text-sm font-semibold text-on-surface">Quyết định hiện tại</span>
                        @if ($ownFlag)
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-sm font-semibold {{ $ownFlag->chipClasses(true) }}">
                                <span class="material-symbols-outlined text-[18px] leading-none {{ $ownFlag->iconClasses(true) }}"
                                    aria-hidden="true"
                                    style="font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 20;">{{ $ownFlag->icon() }}</span>
                                {{ $ownFlag->shortLabel() }}
                            </span>
                        @else
                            <span class="text-sm text-on-surface-variant">—</span>
                        @endif
                    </div>
                    @if (filled($ownNote))
                        <div class="mb-4 rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2.5">
                            <p class="text-xs font-semibold text-on-surface-variant">Ghi chú đã ghi</p>
                            <p class="mt-1 whitespace-pre-wrap text-sm text-on-surface">{{ $ownNote }}</p>
                        </div>
                    @endif
                    <p class="mb-4 text-sm text-on-surface-variant">
                        Giữ nguyên thì không cần thao tác thêm. Chỉ đổi khi đã rà soát kỹ — hệ thống sẽ hỏi xác nhận trách nhiệm.
                    </p>

                    <form method="post" action="{{ route('reviewer.questions.flags.update', $question) }}"
                        x-ref="conflictForm"
                        @submit="if (!isChange) { $event.preventDefault(); return }
                                 if (!acked) { $event.preventDefault(); openAck() }">
                        @csrf
                        @method('PUT')
                        @include('admin::questions.flags.partials.flag-chooser', [
                            'flags' => $flags,
                            'checkedValue' => $ownFlag?->value,
                        ])
                        <label class="mb-1 block text-xs font-semibold text-on-surface-variant" for="conflict-note">
                            <span x-text="flag === 'red' ? 'Ghi chú (bắt buộc khi Không đạt)' : 'Ghi chú (tuỳ chọn)'"></span>
                        </label>
                        <textarea id="conflict-note" name="note" rows="3"
                            :required="isChange && flag === 'red'"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm">{{ old('note', $ownNote) }}</textarea>

                        <input type="hidden" name="responsibility_acked" :value="acked ? '1' : ''">

                        <button type="button" x-show="!isChange" x-cloak
                            class="mt-4 w-full cursor-default rounded-xl border border-outline-variant bg-surface-container-low px-4 py-2.5 font-label-md font-semibold text-on-surface-variant"
                            disabled>
                            Giữ nguyên — không thay đổi
                        </button>
                        <button type="button" x-show="isChange" x-cloak
                            class="mt-4 w-full rounded-xl bg-amber-600 px-4 py-2.5 font-label-md font-semibold text-white"
                            @click="openAck()">
                            Đổi quyết định…
                        </button>
                    </form>

                    <div x-show="showAck" x-cloak
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                        role="dialog" aria-modal="true" aria-labelledby="flag-ack-title">
                        <div class="w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-6 shadow-lg"
                            @click.outside="cancelAck()">
                            <h3 id="flag-ack-title" class="text-base font-semibold text-on-surface">Xác nhận thay đổi</h3>
                            <p class="mt-3 text-sm text-on-surface-variant">{{ $ackText }}</p>
                            <label class="mt-4 flex items-start gap-2 text-sm text-on-surface">
                                <input type="checkbox" class="mt-1" x-model="acked">
                                <span>Tôi đã đọc lại câu hỏi, đáp án, giải thích và chịu trách nhiệm với quyết định này.</span>
                            </label>
                            <div class="mt-5 flex justify-end gap-2">
                                <button type="button" class="rounded-lg px-3 py-2 text-sm text-on-surface-variant"
                                    @click="cancelAck()">Hủy</button>
                                <button type="button"
                                    class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40"
                                    :disabled="!acked"
                                    @click="confirmChange()">
                                    Xác nhận đổi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-outline-variant bg-surface-container-low p-5 text-sm text-on-surface-variant">
                    @if (! $hasFlagPermission)
                        Bạn không có quyền thao tác gắn cờ.
                    @elseif ($ownFlag)
                        <div class="flex items-center gap-2">
                            <span>Bạn đã chọn</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-sm font-semibold {{ $ownFlag->chipClasses(true) }}">
                                <span class="material-symbols-outlined text-[18px] leading-none {{ $ownFlag->iconClasses(true) }}"
                                    aria-hidden="true"
                                    style="font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 20;">{{ $ownFlag->icon() }}</span>
                                {{ $ownFlag->shortLabel() }}
                            </span>
                        </div>
                        <p class="mt-2">Chỉ xem lại nội dung.</p>
                        @if (filled($ownNote))
                            <div class="mt-3 rounded-xl border border-outline-variant bg-surface px-3 py-2.5">
                                <p class="text-xs font-semibold text-on-surface-variant">Ghi chú đã ghi</p>
                                <p class="mt-1 whitespace-pre-wrap text-sm text-on-surface">{{ $ownNote }}</p>
                            </div>
                        @endif
                        @if ($question->status->value === 'flag_conflict')
                            <p class="mt-3">(Câu đang ở Cảnh báo nhưng bạn không còn quyền đổi quyết định.)</p>
                        @endif
                    @else
                        Bạn đã gắn cờ cho câu này — chỉ xem lại nội dung.
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.reviewer>
